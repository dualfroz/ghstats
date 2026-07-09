<?php

declare(strict_types=1);

namespace Ghapi\Collect;

use Ghapi\Config;
use Ghapi\Support\Log;
use Ghapi\Support\Process;

final class Seeder
{
    public function __construct(
        private readonly Config $config,
        private readonly StateStore $store,
        private readonly ?Tokei $tokei,
    ) {
    }

    public function seedRoot(string $root, ?string $only = null): array
    {
        $summary = ['repos' => 0, 'commits' => 0, 'skipped' => []];
        foreach (glob(rtrim($root, '/') . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename(dirname($dir)) . '/' . basename($dir);
            if ($only !== null && strcasecmp($only, $name) !== 0) {
                continue;
            }
            if (!is_dir($dir . '/.git')) {
                $summary['skipped'][] = $name;
                continue;
            }
            $count = $this->seedRepo($dir, $name);
            if ($count === null) {
                $summary['skipped'][] = $name;
                continue;
            }
            $summary['repos']++;
            $summary['commits'] += $count;
        }
        return $summary;
    }

    public function seedRepo(string $dir, string $name): ?int
    {
        $git = ['git', '-c', 'safe.directory=*', '-C', $dir];
        $log = Process::run(array_merge($git, ['log', '--all', '--no-merges', '--numstat', '--format=%x1e%H|%ae|%aI']), null, 900);
        if (!$log->ok()) {
            Log::warn("seed {$name}: git log failed: " . trim($log->stderr));
            return null;
        }
        $commits = self::parseLog($log->stdout, $this->config->authorEmails(), $this->config->isFullCount($name));
        $state = $this->store->loadRepo($name);
        foreach ($commits as $sha => $commit) {
            $existing = $state['commits'][$sha] ?? null;
            if ($existing === null || Commits::isPending($existing)) {
                $state['commits'][$sha] = $commit;
            }
        }
        $head = trim(Process::run(array_merge($git, ['rev-parse', 'HEAD']), null, 30)->stdout);
        if ($head !== '' && ($state['tree']['oid'] ?? null) !== $head) {
            $tree = ['oid' => $head, 'scannedAt' => gmdate('c'), 'languages' => null, 'frameworks' => Frameworks::scan($dir)];
            if ($this->tokei !== null) {
                $tree['languages'] = $this->tokei->scan($dir);
            }
            $state['tree'] = $tree;
        }
        $state['seededAt'] = gmdate('c');
        $this->store->saveRepo($state);
        Log::info("seed {$name}: " . count($commits) . ' own commits');
        return count($commits);
    }

    public static function parseLog(string $output, array $authorEmails, bool $countAll): array
    {
        $emails = array_flip($authorEmails);
        $commits = [];
        foreach (explode("\x1e", $output) as $record) {
            if (trim($record) === '') {
                continue;
            }
            $lines = explode("\n", $record);
            $header = explode('|', trim(array_shift($lines)), 3);
            if (count($header) !== 3) {
                continue;
            }
            [$sha, $email, $date] = $header;
            if (!$countAll && !isset($emails[strtolower($email)])) {
                continue;
            }
            $commit = Commits::make($date);
            if ($commit === null) {
                continue;
            }
            $files = [];
            foreach ($lines as $line) {
                $stat = Commits::parseNumstat($line);
                if ($stat !== null) {
                    Commits::addFile($files, $stat[2], $stat[0], $stat[1]);
                }
            }
            $commits[$sha] = Commits::withFiles($commit, $files);
        }
        return $commits;
    }
}
