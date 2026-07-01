<?php

declare(strict_types=1);

namespace Ghapi\GitHub;

use RuntimeException;

class Source
{
    private const REPO_FIELDS = <<<'GQL'
        databaseId nameWithOwner name isFork isPrivate isArchived isTemplate isEmpty
        stargazerCount forkCount createdAt pushedAt updatedAt description homepageUrl
        owner { login }
        primaryLanguage { name }
        licenseInfo { spdxId }
        defaultBranchRef { name target { oid } }
        refs(refPrefix: "refs/heads/", first: 100) { totalCount pageInfo { hasNextPage endCursor } nodes { name target { oid } } }
        languages(first: 30, orderBy: { field: SIZE, direction: DESC }) { totalSize edges { size node { name color } } }
        GQL;

    public function __construct(private readonly Client $client)
    {
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function repositories(string $owner): array
    {
        $query = 'query($owner: String!, $cursor: String) { repositoryOwner(login: $owner) { login repositories(first: 50, after: $cursor, ownerAffiliations: OWNER) { pageInfo { hasNextPage endCursor } nodes { ' . self::REPO_FIELDS . ' } } } }';
        $repos = [];
        $cursor = null;
        do {
            $data = $this->client->graphql($query, ['owner' => $owner, 'cursor' => $cursor]);
            $connection = $data['repositoryOwner']['repositories'] ?? null;
            if ($connection === null) {
                throw new RuntimeException("Owner {$owner} not found");
            }
            foreach ($connection['nodes'] as $node) {
                if ($node['refs']['pageInfo']['hasNextPage'] ?? false) {
                    $node['refs']['nodes'] = $this->allBranches($node['nameWithOwner']);
                }
                $repos[] = self::normalizeRepo($node);
            }
            $cursor = $connection['pageInfo']['endCursor'];
        } while ($connection['pageInfo']['hasNextPage']);
        return $repos;
    }

    public function allBranches(string $nameWithOwner): array
    {
        [$owner, $name] = explode('/', $nameWithOwner, 2);
        $query = 'query($owner: String!, $name: String!, $cursor: String) { repository(owner: $owner, name: $name) { refs(refPrefix: "refs/heads/", first: 100, after: $cursor) { pageInfo { hasNextPage endCursor } nodes { name target { oid } } } } }';
        $nodes = [];
        $cursor = null;
        do {
            $data = $this->client->graphql($query, ['owner' => $owner, 'name' => $name, 'cursor' => $cursor]);
            $refs = $data['repository']['refs'];
            $nodes = array_merge($nodes, $refs['nodes']);
            $cursor = $refs['pageInfo']['endCursor'];
        } while ($refs['pageInfo']['hasNextPage']);
        return $nodes;
    }

    public static function normalizeRepo(array $node): array
    {
        $branches = [];
        foreach ($node['refs']['nodes'] ?? [] as $ref) {
            if (isset($ref['target']['oid'])) {
                $branches[$ref['name']] = $ref['target']['oid'];
            }
        }
        $languages = [];
        foreach ($node['languages']['edges'] ?? [] as $edge) {
            $languages[] = ['name' => $edge['node']['name'], 'color' => $edge['node']['color'], 'bytes' => (int) $edge['size']];
        }
        return [
            'id' => (int) ($node['databaseId'] ?? 0),
            'name' => $node['nameWithOwner'],
            'owner' => $node['owner']['login'] ?? explode('/', $node['nameWithOwner'])[0],
            'fork' => (bool) $node['isFork'],
            'private' => (bool) $node['isPrivate'],
            'archived' => (bool) $node['isArchived'],
            'template' => (bool) ($node['isTemplate'] ?? false),
            'empty' => (bool) ($node['isEmpty'] ?? false),
            'stars' => (int) $node['stargazerCount'],
            'forks' => (int) $node['forkCount'],
            'createdAt' => $node['createdAt'],
            'pushedAt' => $node['pushedAt'],
            'updatedAt' => $node['updatedAt'],
            'description' => $node['description'],
            'homepage' => $node['homepageUrl'] ?: null,
            'primaryLanguage' => $node['primaryLanguage']['name'] ?? null,
            'license' => $node['licenseInfo']['spdxId'] ?? null,
            'defaultBranch' => $node['defaultBranchRef']['name'] ?? null,
            'defaultHead' => $node['defaultBranchRef']['target']['oid'] ?? null,
            'branches' => $branches,
            'githubLanguages' => $languages,
        ];
    }

    public function history(string $nameWithOwner, string $branch): array
    {
        [$owner, $name] = explode('/', $nameWithOwner, 2);
        $query = 'query($owner: String!, $name: String!, $ref: String!, $cursor: String, $size: Int!) { repository(owner: $owner, name: $name) { ref(qualifiedName: $ref) { target { ... on Commit { history(first: $size, after: $cursor) { pageInfo { hasNextPage endCursor } nodes { oid author { email date } parents { totalCount } } } } } } } }';
        $commits = [];
        $cursor = null;
        $size = 100;
        while (true) {
            try {
                $data = $this->client->graphql($query, ['owner' => $owner, 'name' => $name, 'ref' => 'refs/heads/' . $branch, 'cursor' => $cursor, 'size' => $size]);
            } catch (RuntimeException $failure) {
                if ($size > 25) {
                    $size = intdiv($size, 2);
                    continue;
                }
                throw $failure;
            }
            $history = $data['repository']['ref']['target']['history'] ?? null;
            if ($history === null) {
                return $commits;
            }
            foreach ($history['nodes'] as $node) {
                $commits[$node['oid']] = [
                    'email' => strtolower((string) ($node['author']['email'] ?? '')),
                    'date' => $node['author']['date'] ?? null,
                    'parents' => (int) $node['parents']['totalCount'],
                ];
            }
            if (!$history['pageInfo']['hasNextPage']) {
                return $commits;
            }
            $cursor = $history['pageInfo']['endCursor'];
        }
    }

    public function compare(string $nameWithOwner, string $base, string $head): ?array
    {
        $commits = [];
        $status = null;
        $total = 0;
        $behind = 0;
        for ($page = 1; $page <= 200; $page++) {
            $response = $this->client->rest("/repos/{$nameWithOwner}/compare/{$base}...{$head}", ['per_page' => 100, 'page' => $page]);
            if ($response->status === 404 || $response->status === 422) {
                return null;
            }
            if ($response->status !== 200) {
                throw new RuntimeException("compare {$nameWithOwner} HTTP {$response->status}");
            }
            $data = $response->json();
            $status = $data['status'] ?? null;
            $total = (int) ($data['total_commits'] ?? 0);
            $behind = (int) ($data['behind_by'] ?? 0);
            $pageCommits = $data['commits'] ?? [];
            foreach ($pageCommits as $item) {
                $commits[$item['sha']] = [
                    'email' => strtolower((string) ($item['commit']['author']['email'] ?? '')),
                    'date' => null,
                    'parents' => count($item['parents'] ?? []),
                ];
            }
            if (count($pageCommits) < 100 || count($commits) >= $total) {
                break;
            }
        }
        return ['status' => $status, 'behind' => $behind, 'total' => $total, 'commits' => $commits];
    }

    public function commitDates(string $nameWithOwner, array $shas): array
    {
        [$owner, $name] = explode('/', $nameWithOwner, 2);
        $dates = [];
        foreach (array_chunk(array_values($shas), 50) as $chunk) {
            $fields = [];
            foreach ($chunk as $index => $sha) {
                if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
                    continue;
                }
                $fields[] = "c{$index}: object(oid: \"{$sha}\") { ... on Commit { oid author { date } } }";
            }
            if ($fields === []) {
                continue;
            }
            $data = $this->client->graphql('query($owner: String!, $name: String!) { repository(owner: $owner, name: $name) { ' . implode(' ', $fields) . ' } }', ['owner' => $owner, 'name' => $name]);
            foreach ($data['repository'] ?? [] as $node) {
                if (isset($node['oid'], $node['author']['date'])) {
                    $dates[$node['oid']] = $node['author']['date'];
                }
            }
        }
        return $dates;
    }

    public function commitFiles(string $nameWithOwner, string $sha): ?array
    {
        $files = [];
        for ($page = 1; $page <= 30; $page++) {
            $response = $this->client->rest("/repos/{$nameWithOwner}/commits/{$sha}", ['per_page' => 100, 'page' => $page]);
            if ($response->status === 404 || $response->status === 422) {
                return $page === 1 ? null : $files;
            }
            if ($response->status !== 200) {
                throw new RuntimeException("commit {$nameWithOwner}@{$sha} HTTP {$response->status}");
            }
            $data = $response->json();
            $pageFiles = $data['files'] ?? [];
            foreach ($pageFiles as $file) {
                $files[] = [(string) $file['filename'], (int) ($file['additions'] ?? 0), (int) ($file['deletions'] ?? 0)];
            }
            if (count($pageFiles) < 100) {
                break;
            }
        }
        return $files;
    }

    public function downloadTarball(string $nameWithOwner, string $oid, string $target, int $maxBytes): bool
    {
        $response = $this->client->download("/repos/{$nameWithOwner}/tarball/{$oid}", $target, $maxBytes);
        return $response->status === 200 && is_file($target);
    }

    public function pullRequests(string $login): array
    {
        $query = 'query($q: String!, $cursor: String) { search(query: $q, type: ISSUE, first: 50, after: $cursor) { issueCount pageInfo { hasNextPage endCursor } nodes { ... on PullRequest { number title url state merged isDraft createdAt mergedAt closedAt additions deletions changedFiles commits { totalCount } repository { nameWithOwner url stargazerCount forkCount isPrivate isArchived description primaryLanguage { name color } owner { login } } } } } }';
        $pulls = [];
        $cursor = null;
        do {
            $data = $this->client->graphql($query, ['q' => "author:{$login} is:pr is:public", 'cursor' => $cursor]);
            $search = $data['search'];
            foreach ($search['nodes'] as $node) {
                if (!isset($node['number'])) {
                    continue;
                }
                $pulls[] = $node;
            }
            $cursor = $search['pageInfo']['endCursor'];
        } while ($search['pageInfo']['hasNextPage']);
        return $pulls;
    }

    public function viewer(string $login): array
    {
        $data = $this->client->graphql('query($login: String!) { user(login: $login) { login name followers { totalCount } following { totalCount } repositories(ownerAffiliations: OWNER, privacy: PUBLIC) { totalCount } createdAt avatarUrl bio company location websiteUrl } }', ['login' => $login]);
        return $data['user'] ?? [];
    }
}
