<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Ghapi\Cards\Cards;
use Ghapi\Cards\Theme;
use Ghapi\Collect\Classifier;
use Ghapi\Collect\Collector;
use Ghapi\Collect\Commits;
use Ghapi\Collect\Frameworks;
use Ghapi\Collect\Seeder;
use Ghapi\Collect\SnapshotBuilder;
use Ghapi\Collect\StateStore;
use Ghapi\Config;
use Ghapi\Discord\Interactions;
use Ghapi\GitHub\Source;
use Ghapi\Http\Compat;
use Ghapi\Http\Views;
use Ghapi\Support\Files;
use Ghapi\Support\Labels;

$failures = 0;
$passed = 0;

function check(string $name, bool $condition, string $detail = ''): void
{
    global $failures, $passed;
    if ($condition) {
        $passed++;
        return;
    }
    $failures++;
    fwrite(STDERR, "FAIL {$name}" . ($detail !== '' ? ": {$detail}" : '') . "\n");
}

function same(string $name, mixed $expected, mixed $actual): void
{
    check($name, $expected === $actual, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

same('language ts', 'TypeScript', Classifier::language('src/app/main.tsx'));
same('language header', 'C', Classifier::language('kernel/include/mm.h'));
same('language unknown', null, Classifier::language('README.md'));
same('kind lock', 'lock', Classifier::kind('frontend/package-lock.json'));
same('kind go.sum', 'lock', Classifier::kind('go.sum'));
same('kind test dir', 'test', Classifier::kind('backend/tests/Feature/UserTest.php'));
same('kind go test', 'test', Classifier::kind('router/tokens/tokens_test.go'));
same('kind spec', 'test', Classifier::kind('src/lib/format.spec.ts'));
same('kind java test', 'test', Classifier::kind('src/main/FooTest.java'));
same('kind latest.php is code', 'code', Classifier::kind('app/latest.php'));
same('kind contest.go is code', 'code', Classifier::kind('pkg/contest.go'));
same('kind docs', 'docs', Classifier::kind('docs/guide.md'));
same('kind config', 'config', Classifier::kind('docker-compose.yml'));
same('kind dockerfile', 'config', Classifier::kind('Dockerfile'));
same('kind asset', 'asset', Classifier::kind('public/logo.webp'));
same('rename braces', 'src/new/file.ts', Classifier::normalizeNumstatPath('src/{old => new}/file.ts'));
same('rename plain', 'b.rs', Classifier::normalizeNumstatPath('a.rs => b.rs'));

$log = "\x1eaaa|Me@DualFroz.com|2026-09-01T23:30:00+02:00\n10\t2\tsrc/a.ts\n-\t-\tlogo.png\n5\t0\tsrc/a.test.ts\n"
    . "\x1ebbb|someone@else.dev|2026-09-02T10:00:00+02:00\n1\t1\tx.go\n"
    . "\x1eccc|me@dualfroz.com|2026-09-03T08:15:00+00:00\n\n3\t4\tpackage-lock.json\n";
$parsed = Seeder::parseLog($log, ['me@dualfroz.com'], false);
same('seed own only', ['aaa', 'ccc'], array_keys($parsed));
same('seed added', 15, $parsed['aaa'][Commits::ADDED]);
same('seed removed', 2, $parsed['aaa'][Commits::REMOVED]);
same('seed tz', 120, $parsed['aaa'][Commits::TZ]);
same('seed local date', '2026-09-01', Commits::localDate($parsed['aaa']));
same('seed local hour', 23, Commits::localHour($parsed['aaa']));
same('seed files', ['TypeScript|code' => [10, 2], 'TypeScript|test' => [5, 0], '|asset' => [0, 0]], $parsed['aaa'][Commits::FILES] + []);
same('seed count all', 3, count(Seeder::parseLog($log, [], true)));

$streak = SnapshotBuilder::streak(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-10', '2026-09-12', '2026-09-13'], '2026-09-14');
same('streak current', 2, $streak['current']);
same('streak current start', '2026-09-12', $streak['currentStart']);
same('streak longest', 3, $streak['longest']);
same('streak broken', 0, SnapshotBuilder::streak(['2026-09-01'], '2026-09-14')['current']);
same('project key', 'vulcode', SnapshotBuilder::projectKey('VulCodeCom/vulcode-frontend'));
same('project key plain', 'lunatic-chess', SnapshotBuilder::projectKey('x/lunatic-chess'));
same('percentile', 3, SnapshotBuilder::percentile([5, 1, 3, 2, 4], 0.5));

$tmp = sys_get_temp_dir() . '/ghapi-tests-' . bin2hex(random_bytes(4));
Files::ensureDir($tmp . '/repo/web');
file_put_contents($tmp . '/repo/web/package.json', json_encode(['dependencies' => ['react' => '^18', 'gsap' => '^3'], 'devDependencies' => ['vitest' => '^1']]));
file_put_contents($tmp . '/repo/go.mod', "module x\n\ngo 1.24\n\nrequire (\n\tgithub.com/gin-gonic/gin v1.10.0\n\tgorm.io/gorm v1.25.0\n)\n");
file_put_contents($tmp . '/repo/Cargo.toml', "[package]\nname = \"x\"\n\n[dependencies]\ntokio = { version = \"1\" }\naxum = \"0.7\"\n");
file_put_contents($tmp . '/repo/App.csproj', '<Project Sdk="Microsoft.NET.Sdk.Web"><ItemGroup><PackageReference Include="Microsoft.EntityFrameworkCore.Sqlite" Version="8.0.0" /></ItemGroup></Project>');
Files::ensureDir($tmp . '/repo/node_modules/next');
file_put_contents($tmp . '/repo/node_modules/next/package.json', json_encode(['dependencies' => ['next' => '1']]));
$names = array_column(Frameworks::scan($tmp . '/repo'), 'name');
foreach (['React', 'GSAP', 'Vitest', 'Gin', 'GORM', 'Tokio', 'Axum', 'ASP.NET Core', 'Entity Framework Core'] as $expected) {
    check("framework {$expected}", in_array($expected, $names, true), implode(', ', $names));
}
check('framework skips node_modules', !in_array('Next.js', $names, true));

$aliasConfig = Config::fromArray([]);
same('alias is case-insensitive', 'TypeScript', $aliasConfig->canonicalLanguage('TSX'));
same('alias jsx', 'JavaScript', $aliasConfig->canonicalLanguage('JSX'));
same('denylist', null, $aliasConfig->canonicalLanguage('json'));
same('tokei alias', 'TypeScript', (new Ghapi\Collect\Tokei('tokei', ['Tsx' => 'TypeScript'], []))->canonical('TSX'));

$keypair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keypair);
$publicHex = bin2hex(sodium_crypto_sign_publickey($keypair));
$timestamp = (string) time();
$body = '{"type":1}';
$signature = bin2hex(sodium_crypto_sign_detached($timestamp . $body, $secret));
check('discord signature valid', Interactions::verify($publicHex, $signature, $timestamp, $body));
check('discord signature tampered', !Interactions::verify($publicHex, $signature, $timestamp, '{"type":2}'));
check('discord signature old', !Interactions::verify($publicHex, bin2hex(sodium_crypto_sign_detached('100' . $body, $secret)), '100', $body));

$config = Config::fromArray(['stateDir' => $tmp . '/state', 'snapshotDir' => $tmp . '/snapshot', 'owners' => ['dualfroz'], 'timezone' => 'Europe/Warsaw']);
$store = new StateStore($config->string('stateDir'));

final class FakeSource extends Source
{
    public array $calls = [];

    public function __construct(private array $history, private array $comparisons = [], private array $dates = [])
    {
    }

    public function history(string $nameWithOwner, string $branch): array
    {
        $this->calls[] = "history:{$branch}";
        return $this->history[$branch] ?? [];
    }

    public function compare(string $nameWithOwner, string $base, string $head): ?array
    {
        $this->calls[] = "compare:{$base}...{$head}";
        return $this->comparisons["{$base}...{$head}"] ?? null;
    }

    public function commitDates(string $nameWithOwner, array $shas): array
    {
        return array_intersect_key($this->dates, array_flip($shas));
    }
}

$remote = [
    'name' => 'dualfroz/demo', 'owner' => 'dualfroz', 'fork' => false, 'private' => true, 'archived' => false, 'empty' => false,
    'stars' => 3, 'forks' => 0, 'defaultBranch' => 'main', 'defaultHead' => 'h2', 'branches' => ['main' => 'h2'],
];
$me = static fn (string $date, int $parents = 1): array => ['email' => 'me@dualfroz.com', 'date' => $date, 'parents' => $parents];
$source = new FakeSource(['main' => [
    'h2' => $me('2026-09-12T10:00:00+02:00'),
    'm1' => $me('2026-09-11T10:00:00+02:00', 2),
    'x1' => ['email' => 'bot@example.com', 'date' => '2026-09-10T10:00:00Z', 'parents' => 1],
    'h1' => $me('2026-09-09T10:00:00+02:00'),
]]);
$collector = new Collector($config, $store, $source, null, new SnapshotBuilder($config, $store));
$state = $store->loadRepo('dualfroz/demo');
$state['commits']['gone'] = Commits::make('2026-01-01T00:00:00Z', 1, 1, []);
$state['commits']['h1'] = Commits::make('2026-09-09T10:00:00+02:00', 7, 3, ['Go|code' => [7, 3]]);
$result = $collector->syncRepo($remote, $state, false);
same('full walk on first sync', true, $result['full']);
same('full walk purges unreachable', 1, $result['purged']);
same('full walk adds own non-merge', ['h1', 'h2'], array_keys(array_filter($state['commits'], static fn ($c) => true)));
same('seeded commit keeps details', [7, 3], [$state['commits']['h1'][Commits::ADDED], $state['commits']['h1'][Commits::REMOVED]]);
check('new commit pending', Commits::isPending($state['commits']['h2']));

$remote['defaultHead'] = 'h4';
$remote['branches'] = ['main' => 'h4', 'feature' => 'f1'];
$source = new FakeSource([], [
    'h2...h4' => ['status' => 'ahead', 'behind' => 0, 'total' => 2, 'commits' => ['h3' => ['email' => 'me@dualfroz.com', 'date' => null, 'parents' => 1], 'h4' => ['email' => 'other@x.dev', 'date' => null, 'parents' => 1]]],
    'h2...f1' => ['status' => 'ahead', 'behind' => 0, 'total' => 1, 'commits' => ['f1' => ['email' => 'me@dualfroz.com', 'date' => null, 'parents' => 1]]],
], ['h3' => '2026-09-13T09:00:00+02:00', 'f1' => '2026-09-13T11:00:00+02:00']);
$collector = new Collector($config, $store, $source, null, new SnapshotBuilder($config, $store));
$result = $collector->syncRepo($remote, $state, false);
same('incremental no full walk', false, $result['full']);
same('incremental adds', 2, $result['added']);
same('incremental uses compare', ['compare:h2...h4', 'compare:h2...f1'], $source->calls);

$remote['branches'] = ['main' => 'r1', 'feature' => 'f1'];
$remote['defaultHead'] = 'r1';
$source = new FakeSource(['main' => ['r1' => $me('2026-09-13T12:00:00+02:00'), 'h1' => $me('2026-09-09T10:00:00+02:00')]], [
    'h4...r1' => ['status' => 'diverged', 'behind' => 2, 'total' => 1, 'commits' => []],
    'r1...f1' => ['status' => 'ahead', 'behind' => 0, 'total' => 1, 'commits' => ['f1' => $me('2026-09-13T11:00:00+02:00')]],
]);
$collector = new Collector($config, $store, $source, null, new SnapshotBuilder($config, $store));
$result = $collector->syncRepo($remote, $state, false);
same('force push triggers full walk', true, $result['full']);
same('force push purges rewritten commits', ['h1', 'f1', 'r1'], array_keys($state['commits']));
$store->saveRepo($state);

$builder = new SnapshotBuilder($config, $store, strtotime('2026-09-14 12:00:00 UTC'));
$snapshot = $builder->build();
same('snapshot commits', 3, $snapshot['totals']['commits']);
same('snapshot pending', 2, $snapshot['totals']['pendingDetails']);
same('snapshot today', '2026-09-14', $snapshot['today']);
$labels = Labels::of('pl');
$compare = Views::compare($snapshot, 'week', $labels);
same('compare week start', '2026-09-14', $compare['current']['from']);
same('compare previous week', ['2026-09-07', '2026-09-13'], [$compare['previous']['from'], $compare['previous']['to']]);
same('compare previous commits', 3, $compare['previous']['commits']);
$stripped = Views::strip($snapshot);
check('strip removes repos', !isset($stripped['repos']));
same('commits by month', [['period' => '2026-09', 'commits' => 3, 'linesAdded' => 7, 'linesRemoved' => 3, 'activeDays' => 2]], Views::commits($snapshot, 'month', 'all', $labels)['series']);
$compat = Compat::stats($snapshot);
same('compat commits', 3, $compat['ownCommits']);
same('compat last year window', 371, count($compat['commitsLastYear']['days']));
foreach (Cards::NAMES as $card) {
    libxml_use_internal_errors(true);
    $svg = (new Cards($snapshot, Theme::resolve('light'), $labels))->render($card);
    check("card {$card} is valid svg", $svg !== null && simplexml_load_string($svg) !== false);
}
Files::removeTree($tmp);

fwrite(STDOUT, "passed {$passed}, failed {$failures}\n");
exit($failures === 0 ? 0 : 1);
