<?php

declare(strict_types=1);

namespace Ghapi\Collect;

use Ghapi\Config;
use Ghapi\GitHub\Source;
use Ghapi\Support\Files;
use Ghapi\Support\Log;
use Ghapi\Support\Process;
use RuntimeException;
use Throwable;

final class Collector
{
    private array $authors;

    public function __construct(
        private readonly Config $config,
        private readonly StateStore $store,
        private readonly Source $source,
        private readonly ?Tokei $tokei,
        private readonly SnapshotBuilder $builder,
        private readonly ?Notifier $notifier = null,
    ) {
        $this->authors = array_flip($config->authorEmails());
    }

    public function run(array $options = []): array
    {
        $lock = fopen($this->store->dir() . '/collector.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['status' => 'locked'];
        }
        try {
            return $this->runLocked($options);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runLocked(array $options): array
    {
        $started = time();
        $meta = $this->store->load('meta') ?? [];
        $full = (bool) ($options['full'] ?? false);
        $force = (bool) ($options['force'] ?? false) || $full;
        $only = $options['only'] ?? null;
        $lastRefresh = isset($meta['lastRefreshAt']) ? strtotime((string) $meta['lastRefreshAt']) : 0;
        $due = $force || $only !== null || $started - $lastRefresh >= $this->config->int('refreshHours') * 3600 - 600;
        $pending = (int) ($meta['pendingDetails'] ?? 0);
        if (!$due && $pending === 0) {
            return ['status' => 'idle', 'nextRefreshAt' => gmdate('c', $lastRefresh + $this->config->int('refreshHours') * 3600)];
        }

        $report = ['status' => 'ok', 'refreshed' => $due, 'repos' => 0, 'newCommits' => 0, 'purged' => 0, 'details' => 0, 'trees' => 0, 'errors' => []];
        $remotes = [];
        if ($due) {
            $remotes = $this->enumerate($report);
            if ($remotes === []) {
                throw new RuntimeException('Enumeration returned no repositories, refusing to continue');
            }
            $this->syncAll($remotes, $full, $only, $report);
            if ($only === null) {
                $this->pruneMissing($remotes, $report);
            }
        }

        $this->fetchDetails($only, $report);

        if ($due && !($options['skipTrees'] ?? false)) {
            $this->scanTrees($remotes, $only, $report);
        }

        if ($due && $only === null && !($options['skipOss'] ?? false)) {
            try {
                $profile = $this->source->viewer($this->config->string('login'));
                $pulls = $this->source->pullRequests($this->config->string('login'));
                $this->store->save('opensource', ['fetchedAt' => gmdate('c'), 'profile' => $profile, 'pulls' => $pulls]);
            } catch (Throwable $failure) {
                $report['errors'][] = 'opensource: ' . $failure->getMessage();
                Log::warn('opensource fetch failed: ' . $failure->getMessage());
            }
        }

        $pendingNow = $this->countPending();
        $meta['lastRunAt'] = gmdate('c', $started);
        $meta['lastRunSeconds'] = time() - $started;
        $meta['pendingDetails'] = $pendingNow;
        if ($due) {
            $meta['lastRefreshAt'] = gmdate('c', $started);
            if ($full) {
                $meta['lastFullAt'] = gmdate('c', $started);
            }
        }
        $meta['rateLimit'] = [
            'core' => $this->source->client()->remaining('core'),
            'graphql' => $this->source->client()->remaining('graphql'),
            'requests' => $this->source->client()->requestCount(),
        ];
        $meta['lastReport'] = $report;
        $this->store->save('meta', $meta);

        $previous = $this->builder->currentTotals();
        $snapshot = $this->builder->build($meta);
        $report['pendingDetails'] = $pendingNow;
        $report['generatedAt'] = $snapshot['generatedAt'];

        if ($due && $this->notifier !== null) {
            try {
                $this->notifier->refreshed($previous, $snapshot, $report);
            } catch (Throwable $failure) {
                Log::warn('webhook failed: ' . $failure->getMessage());
            }
        }
        return $report;
    }

    private function enumerate(array &$report): array
    {
        $remotes = [];
        foreach ($this->config->list('owners') as $owner) {
            try {
                foreach ($this->source->repositories((string) $owner) as $repo) {
                    $remotes[strtolower($repo['name'])] = $repo;
                }
            } catch (Throwable $failure) {
                $report['errors'][] = "enumerate {$owner}: " . $failure->getMessage();
                Log::warn("enumerate {$owner} failed: " . $failure->getMessage());
                throw new RuntimeException("Enumeration of {$owner} failed, aborting to avoid dropping its repositories");
            }
        }
        ksort($remotes);
        $this->store->save('repositories', ['fetchedAt' => gmdate('c'), 'repositories' => array_values($remotes)]);
        return $remotes;
    }

    private function countable(array $remote): bool
    {
        return !$remote['fork'] && !$this->config->isExcluded($remote['name']);
    }

    private function syncAll(array $remotes, bool $full, ?string $only, array &$report): void
    {
        foreach ($remotes as $remote) {
            if (!$this->countable($remote) || ($only !== null && strcasecmp($only, $remote['name']) !== 0)) {
                continue;
            }
            $report['repos']++;
            $state = $this->store->loadRepo($remote['name']);
            try {
                $changes = $this->syncRepo($remote, $state, $full);
                $report['newCommits'] += $changes['added'];
                $report['purged'] += $changes['purged'];
                $this->store->saveRepo($state);
                if ($changes['added'] > 0 || $changes['purged'] > 0) {
                    Log::info("{$remote['name']}: +{$changes['added']} commits, -{$changes['purged']} purged" . ($changes['full'] ? ' (full walk)' : ''));
                }
            } catch (Throwable $failure) {
                $report['errors'][] = "{$remote['name']}: " . $failure->getMessage();
                Log::warn("sync {$remote['name']} failed: " . $failure->getMessage());
            }
        }
    }

    public function syncRepo(array $remote, array &$state, bool $full): array
    {
        $state['name'] = $remote['name'];
        $state['meta'] = array_diff_key($remote, ['branches' => true]);
        $result = ['added' => 0, 'purged' => 0, 'full' => false];
        if ($remote['empty'] || $remote['defaultHead'] === null) {
            $result['purged'] = count($state['commits']);
            $state['commits'] = [];
            $state['heads'] = [];
            return $result;
        }

        $countAll = $this->config->isFullCount($remote['name']);
        $needFull = $full || $state['heads'] === [] || $state['walkedAt'] === null;
        $discovered = [];

        if (!$needFull) {
            foreach ($remote['branches'] as $branch => $oid) {
                $old = $state['heads'][$branch] ?? null;
                if ($old === $oid || isset($state['commits'][$oid])) {
                    continue;
                }
                $base = $old ?? ($state['heads'][$remote['defaultBranch']] ?? null);
                if ($base === null || $base === $oid) {
                    $needFull = $base === null;
                    if ($needFull) {
                        break;
                    }
                    continue;
                }
                $comparison = $this->source->compare($remote['name'], $base, $oid);
                if ($comparison === null || ($old !== null && $comparison['behind'] > 0)) {
                    $needFull = true;
                    break;
                }
                $discovered += $comparison['commits'];
            }
        }

        if ($needFull) {
            $result['full'] = true;
            $reachable = $this->source->history($remote['name'], (string) $remote['defaultBranch']);
            if ($reachable === []) {
                throw new RuntimeException('default branch history came back empty');
            }
            foreach ($remote['branches'] as $branch => $oid) {
                if ($branch === $remote['defaultBranch'] || isset($reachable[$oid])) {
                    continue;
                }
                $comparison = $this->source->compare($remote['name'], (string) $remote['defaultHead'], $oid);
                $reachable += $comparison === null ? $this->source->history($remote['name'], (string) $branch) : $comparison['commits'];
            }
            foreach (array_keys($state['commits']) as $sha) {
                if (!isset($reachable[$sha])) {
                    unset($state['commits'][$sha]);
                    $result['purged']++;
                }
            }
            $discovered = $reachable;
        }

        $fresh = [];
        foreach ($discovered as $sha => $info) {
            if (isset($state['commits'][$sha]) || $info['parents'] > 1) {
                continue;
            }
            if (!$countAll && !isset($this->authors[$info['email']])) {
                continue;
            }
            $fresh[$sha] = $info;
        }
        $missingDates = array_keys(array_filter($fresh, static fn (array $info): bool => $info['date'] === null));
        if ($missingDates !== []) {
            foreach ($this->source->commitDates($remote['name'], $missingDates) as $sha => $date) {
                $fresh[$sha]['date'] = $date;
            }
        }
        foreach ($fresh as $sha => $info) {
            if ($info['date'] === null) {
                continue;
            }
            $commit = Commits::make($info['date']);
            if ($commit !== null) {
                $state['commits'][$sha] = $commit;
                $result['added']++;
            }
        }

        $state['heads'] = $remote['branches'];
        $state['walkedAt'] = gmdate('c');
        return $result;
    }

    private function pruneMissing(array $remotes, array &$report): void
    {
        foreach ($this->store->allRepoNames() as $name => $file) {
            $remote = $remotes[strtolower($name)] ?? null;
            if ($remote === null || !$this->countable($remote)) {
                $this->store->removeRepo($name);
                $report['purged']++;
                Log::info("{$name}: no longer tracked, state removed");
            }
        }
    }

    private function fetchDetails(?string $only, array &$report): void
    {
        $client = $this->source->client();
        $reserve = $this->config->int('restBudgetReserve');
        $limit = $this->config->int('maxDetailRequestsPerRun');
        foreach ($this->store->allRepoNames() as $name => $file) {
            if ($only !== null && strcasecmp($only, $name) !== 0) {
                continue;
            }
            $state = $this->store->loadRepo($name);
            $changed = 0;
            foreach ($state['commits'] as $sha => $commit) {
                if (!Commits::isPending($commit)) {
                    continue;
                }
                $remaining = $client->remaining('core');
                if ($report['details'] >= $limit || ($remaining !== null && $remaining <= $reserve)) {
                    break 2;
                }
                try {
                    $files = $this->source->commitFiles($name, (string) $sha);
                } catch (Throwable $failure) {
                    $report['errors'][] = "details {$name}@{$sha}: " . $failure->getMessage();
                    continue;
                }
                $aggregated = [];
                foreach ($files ?? [] as [$path, $added, $removed]) {
                    Commits::addFile($aggregated, $path, $added, $removed);
                }
                $state['commits'][$sha] = Commits::withFiles($commit, $aggregated);
                $report['details']++;
                if (++$changed % 200 === 0) {
                    $this->store->saveRepo($state);
                }
            }
            if ($changed > 0) {
                $this->store->saveRepo($state);
            }
        }
        if ($changed ?? 0) {
            $this->store->saveRepo($state);
        }
    }

    private function countPending(): int
    {
        $pending = 0;
        foreach ($this->store->allRepoNames() as $name => $file) {
            foreach ($this->store->loadRepo($name)['commits'] as $commit) {
                if (Commits::isPending($commit)) {
                    $pending++;
                }
            }
        }
        return $pending;
    }

    private function scanTrees(array $remotes, ?string $only, array &$report): void
    {
        $tmpRoot = $this->config->string('tmpDir');
        Files::ensureDir($tmpRoot, 0750);
        foreach ($remotes as $remote) {
            if (!$this->countable($remote) || $remote['empty'] || $remote['defaultHead'] === null) {
                continue;
            }
            if ($only !== null && strcasecmp($only, $remote['name']) !== 0) {
                continue;
            }
            $state = $this->store->loadRepo($remote['name']);
            if ($state['commits'] === [] || ($state['tree']['oid'] ?? null) === $remote['defaultHead']) {
                continue;
            }
            $remaining = $this->source->client()->remaining('core');
            if ($remaining !== null && $remaining <= $this->config->int('restBudgetReserve')) {
                break;
            }
            $work = $tmpRoot . '/' . preg_replace('/[^a-z0-9]+/', '-', strtolower($remote['name'])) . '-' . substr((string) $remote['defaultHead'], 0, 12);
            $archive = $work . '.tar.gz';
            Files::removeTree($work);
            try {
                if (!$this->source->downloadTarball($remote['name'], (string) $remote['defaultHead'], $archive, $this->config->int('maxTarballBytes'))) {
                    throw new RuntimeException('tarball download failed');
                }
                Files::ensureDir($work, 0750);
                $untar = Process::run([$this->config->string('tarBin'), '-xzf', $archive, '-C', $work, '--strip-components=1', '--no-same-owner', '--no-same-permissions'], null, 600);
                @unlink($archive);
                if (!$untar->ok()) {
                    throw new RuntimeException('tar failed: ' . trim($untar->stderr));
                }
                $state['tree'] = [
                    'oid' => $remote['defaultHead'],
                    'scannedAt' => gmdate('c'),
                    'languages' => $this->tokei?->scan($work),
                    'frameworks' => Frameworks::scan($work),
                ];
                $this->store->saveRepo($state);
                $report['trees']++;
            } catch (Throwable $failure) {
                $report['errors'][] = "tree {$remote['name']}: " . $failure->getMessage();
                Log::warn("tree scan {$remote['name']} failed: " . $failure->getMessage());
            } finally {
                @unlink($archive);
                Files::removeTree($work);
            }
        }
    }
}
