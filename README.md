# ghapi

ghapi is a self-hosted GitHub statistics service written in plain PHP. A collector reads every repository of
the configured accounts and organizations through the GitHub API, counts the commits written by you (matched by
author email) down to exact per-file line counts, and writes ready-made JSON snapshots. A small HTTP front end
serves those snapshots as a JSON API and as SVG cards for profile READMEs and websites, and a Discord app
answers the same questions as slash commands. Private repositories are counted, but their names never leave the
authenticated part of the API.

## Features

Collector (`bin/ghapi collect`):

- Enumerates all repositories of every account in `owners` with one GraphQL query per page (branches, heads,
  stars, forks, languages, license, default branch).
- Incremental sync per branch: an unchanged head costs nothing, a moved head is diffed with the compare API
  (`old...new`), a new branch against the default branch. Commits with old author dates (backdated, pushed late)
  are caught because nothing depends on dates.
- Force pushes and rewritten history are detected (compare reports the old head as behind) and trigger a full
  walk; commits that are no longer reachable are dropped. Repositories that disappear are removed from the state.
- Only your commits count: author email in `authorEmails`, merge commits skipped, forks never counted.
  Repositories listed in `fullCountRepos` count every author; `excludeRepos` are ignored completely.
- Each counted commit gets its file list from the commit API once, so lines added and removed are split exactly
  by language and by file kind (code, test, docs, config, lock, data, asset, other).
- When the default branch moves, its tarball is downloaded to a temporary directory, scanned with `tokei` (living
  code per language, bytes) and for framework manifests, then deleted. No clone is kept on disk. Without `tokei`
  the living code falls back to GitHub language bytes.
- Framework detection from `package.json`, `deno.json`, `composer.json`, `go.mod`, `Cargo.toml`,
  `requirements.txt`, `Pipfile`, `pyproject.toml`, `pom.xml`, `build.gradle(.kts)`, `mix.exs`, `pubspec.yaml`,
  `Gemfile`, `Package.swift`, `*.csproj` and `*.fsproj`, plus Unity, Godot and Docker markers. `node_modules`,
  `vendor`, build output and similar directories are skipped.
- Public pull requests of `login` feed the open source numbers.
- Rate limit aware: stops detail fetching when the REST budget drops to `restBudgetReserve`, caps detail requests
  per run with `maxDetailRequestsPerRun`, retries with backoff on network errors, 429, 500, 502, 503, 504 and
  secondary rate limits.
- A full refresh runs once every `refreshHours`; runs in between only finish commit details that did not fit into
  the rate limit. A lock file prevents overlapping runs.
- Optional Discord webhook summary after every full refresh, with the change since the previous snapshot.
- Snapshots are written atomically, one per scope: all accounts together and one per owner.

Seeding (`bin/ghapi seed <dir>`): reads `git log --all --no-merges --numstat` and `tokei` from existing clones laid
out as `<owner>/<repo>`, so the first `collect` only fetches what the clones did not have instead of one API call
per historical commit. The clones can be deleted afterwards.

HTTP API (`public/index.php`):

- Reads snapshots only and never calls GitHub, so request volume costs file reads.
- Everything under `/v2/public` is open, sends `Access-Control-Allow-Origin: *` and contains no private
  repository names, SHAs or per-repository data. The same paths under `/v2` without `public/` need
  `Authorization: Bearer <key>` and add them. API keys are stored as SHA-256 hashes.
- Every data endpoint accepts `owner=<account>` (one scope), `lang=en` for English labels (Polish is the default)
  and `pretty=1`. Responses carry an `ETag` derived from the snapshot version and answer `If-None-Match` with 304.

| Path | Content |
|------|---------|
| `/v2`, `/v2/public` | index of endpoints and known owners |
| `/v2/health` | snapshot age, status `ok`, `stale` (older than two days) or `no_data`, pending commit details |
| `summary` | totals, streak, top languages and frameworks, tests, commit size, lifetime, open source |
| `commits?group=year\|month\|week\|day\|hour\|weekday\|punchcard&range=all\|365d\|90d\|30d&from=&to=` | commit counts; hour and weekday use the author's local time |
| `commits/hours`, `commits/weekdays`, `commits/punchcard` | shortcuts for the groups above |
| `commits/size` | size buckets, mean, median, p75, p90, p99, largest commits, clean-up commits (more removed than added) |
| `streak` | current and longest streak with start and end dates |
| `loc?group=year\|month\|week\|day&range=&from=&to=` | lines added and removed per period, file kinds |
| `languages?limit=` | lines added and removed, share, commits, repositories, first and last year, bytes, test lines |
| `frameworks?ecosystem=` | detected frameworks and tools with the number of repositories, per ecosystem |
| `tests` | lines added in test files against other source, per language (and per repository when authenticated) |
| `compare?period=week\|month` | this period so far against the previous one, in full and up to the same day |
| `opensource?limit=` | pull requests to public projects, contributed projects with stars, own public repositories |
| `lifetime` | repositories active, maintained, dormant, inactive, archived; median and longest lifetime |
| `repos?status=&visibility=&language=&sort=commits\|lines\|recent\|stars\|lifetime\|name&limit=` | repository list (public ones only under `/v2/public`) |
| `repos/{owner}/{name}` | one repository with a monthly series |
| `meta` | authenticated only: last collector run, rate limits, errors, scopes |
| `cards/{name}.svg` | SVG cards, see below |

SVG cards: `stats`, `top-langs`, `streak`, `activity`, `heatmap`, `loc`, `by-year`, `top-repos`, `punchcard`,
`achievements`, `opensource`, `frameworks`, `hours` and `discord`. Parameters: `theme=tokyonight|light`,
`hide_border`, `range=all` (heatmap and activity cover all time instead of 52 weeks), `name=` (title override, up
to 40 characters), `lang`, `owner`. The public `top-repos` card lists open source contributions; the
authenticated one lists your most active repositories.

The `discord` card renders a Discord profile (banner, avatar, status, display name, pronouns, badges, bio,
member since, Spotify or game activity). Discord does not expose presence to an HTTP endpoint, so the card reads
a JSON file written by a bot you run yourself (`discordProfileFile`). Images from the Discord CDN and Spotify are
inlined as data URIs and cached for a day; presence older than 15 minutes is shown as offline. Bio, pronouns,
the fallback banner and extra badges come from the config because bots cannot read them.

The profile file looks like this (all keys except `username` are optional):

```json
{
    "userId": "123456789012345678",
    "username": "octocat",
    "globalName": "Octocat",
    "status": "online",
    "avatar": "https://cdn.discordapp.com/avatars/...png?size=128",
    "banner": null,
    "accentColor": 5793266,
    "publicFlags": 4194304,
    "clanTag": "OCTO",
    "clanBadge": null,
    "updatedAt": 1767225600,
    "activities": [
        { "type": "custom", "emoji": "", "name": "shipping" },
        { "type": "spotify", "title": "Song", "artist": "Artist", "album": "Album", "albumArt": "https://i.scdn.co/image/...", "start": 1767225500000, "end": 1767225700000 },
        { "type": "playing", "name": "Game", "details": null, "state": null }
    ]
}
```

Compatibility paths: `/data/stats` (flat JSON with totals, languages, last 30 days and last year of commits, commits
per year, lines per day), `/healthz`, and every card name at the root (`/stats`, `/top-langs`, ..., `/discord`).

Discord app (`POST /v2/discord/interactions`): a user-installable app, so it works in DMs, group chats and any
server without adding a bot. Requests are verified with the application's Ed25519 public key. Slash commands:
`/help`, `/summary`, `/loc`, `/commits`, `/langs`, `/oss`, `/repo`, `/compare`, `/frameworks`, `/sizes`,
`/tests`, each with `lang` and `hidden` (answer visible only to you); `owner` and `repo` names autocomplete. The
user in `discordUserId` gets the authenticated view, everyone else the public one.

## Requirements

- PHP 8.2 or newer with `openssl`, `sodium` and `allow_url_fopen` enabled. No Composer dependencies.
- A GitHub token in `GH_TOKEN` with read access to the repositories you want counted (for private ones a classic
  token with `repo` or a fine-grained token with Contents and Metadata read access).
- `tar` for tree scans, optionally `tokei` for living code, `git` for `seed`.
- PHP-FPM behind a web server for the API.

## Configuration

Configuration is JSON, read from `GHAPI_CONFIG` (default `/etc/ghapi/config.json`) and merged with
`GHAPI_CONFIG_EXTRA` when set. Keeping secrets that only the collector needs (the webhook, the bot token file) in
the extra file lets the web server read the main config without them. Templates: `deploy/config.example.json`,
`deploy/collector.example.json` and `.env.example`.

| Key | Meaning |
|-----|---------|
| `owners` | accounts and organizations whose repositories are counted (required) |
| `login`, `name` | your GitHub login (pull requests, card scope) and display name (required: `login`) |
| `authorEmails` | commit author emails that count as yours (required) |
| `fullCountRepos`, `excludeRepos` | `owner/name` or `name` counted for every author; `owner/name` ignored |
| `timezone` | timezone that defines "today" for streaks and comparisons (default `UTC`) |
| `stateDir`, `snapshotDir`, `tmpDir`, `cacheDir` | collector state, snapshots, tarball scratch space, card image cache |
| `tokeiBin`, `tarBin` | binaries used for tree scans |
| `refreshHours`, `restBudgetReserve`, `maxDetailRequestsPerRun`, `maxTarballBytes` | collector limits |
| `languageAliases`, `languageDenylist` | `tokei` language names mapped or dropped |
| `apiKeys` | list of `{ "label": ..., "sha256": ... }`; `bin/ghapi key <label>` prints a new key and its entry |
| `publicBaseUrl` | base URL used in the endpoint index, webhook and Discord help |
| `cacheSeconds`, `heatmapThresholds` | `Cache-Control` max age; commit counts for the heatmap levels |
| `discordWebhook` | webhook URL for refresh summaries |
| `discordApplicationId`, `discordPublicKey`, `discordBotTokenFile`, `discordUserId` | Discord app |
| `discordProfileFile`, `discordBio`, `discordPronouns`, `discordBanner`, `discordBadges` | `discord` card; banner is `#rrggbb` or `#from,#to`, badges are file names from `assets/badges` |

## Running locally

```
cp .env.example .env
cp deploy/config.example.json config.json
# edit .env and config.json, point the directories somewhere writable
set -a; . ./.env; set +a
export GHAPI_CONFIG=$PWD/config.json GHAPI_CONFIG_EXTRA=
php bin/ghapi collect --force
php -S 127.0.0.1:8080 -t public public/index.php
curl 'http://127.0.0.1:8080/v2/public/summary?pretty=1'
```

CLI reference:

```
ghapi collect [--force] [--full] [--only=owner/repo] [--skip-trees] [--skip-oss]
ghapi seed [/srv/ghapi/repos] [--only=owner/repo]
ghapi snapshot
ghapi key <label>
ghapi discord:register
```

`--force` refreshes now, `--full` also walks the full history of every repository, `snapshot` rebuilds snapshots
from the stored state, `discord:register` uploads the slash commands.

## Deployment

The files in `deploy/` describe one setup: a `ghapi` system user running the collector from a systemd timer
(hourly, full refresh once a day), PHP-FPM serving `public/` through nginx with a request rate limit. Replace
`ghapi.example.com`, `/opt/ghapi` and the PHP-FPM socket with your own values.

```
useradd --system --home /srv/ghapi --shell /usr/sbin/nologin ghapi
install -d -o ghapi -g ghapi -m 0750 /srv/ghapi/state /srv/ghapi/tmp
install -d -o ghapi -g ghapi -m 0755 /srv/ghapi /srv/ghapi/snapshot
install -d -o www-data -g www-data -m 0750 /srv/ghapi/cache
install -d -m 0755 /etc/ghapi
cp deploy/config.example.json /etc/ghapi/config.json
cp deploy/collector.example.json /etc/ghapi/collector.json
install -m 0600 .env.example /etc/ghapi/github.env
cp deploy/ghapi-collector.service deploy/ghapi-collector.timer /etc/systemd/system/
cp deploy/ghapi-limits.conf /etc/nginx/conf.d/
cp deploy/nginx-ghapi.conf /etc/nginx/sites-available/ghapi
systemctl daemon-reload && systemctl enable --now ghapi-collector.timer
nginx -t && systemctl reload nginx
```

Set `GH_TOKEN` in `/etc/ghapi/github.env`, and make `config.json` readable by the web server and the collector
and `collector.json` by the collector only.

Discord app setup in the Developer Portal: under Installation enable only User Install with the Discord Provided
Link and the `applications.commands` scope; put the application ID and public key into the config; set the
Interactions Endpoint URL to `https://<your host>/v2/discord/interactions`; save the bot token to the file named
in `discordBotTokenFile` and run `bin/ghapi discord:register`.

## Tests

```
php tests/run.php
```

A dependency-free runner covering file classification, `git log` parsing, streaks, framework detection, language
aliases, Discord signature verification, incremental sync, force-push handling against a fake GitHub source,
snapshot building, views, the compatibility endpoint and SVG validity of every card.

## Project layout

```
bin/ghapi              CLI: collect, seed, snapshot, key, discord:register
public/index.php       HTTP entry point
src/Config.php         configuration defaults and loading
src/GitHub/            GitHub GraphQL and REST client, repository and commit queries
src/Collect/           collector, seeder, state store, snapshot builder, classifiers, tokei, webhook
src/Http/              router, request and response, views over snapshots, compatibility endpoint
src/Cards/             SVG cards and themes
src/Discord/           interactions endpoint, slash command definitions, profile card
src/Support/           HTTP, JSON, files, processes, labels, language colors
assets/badges/         Discord badge images used by the profile card
deploy/                example systemd, nginx and config files
tests/run.php          test runner
```

## Limitations

- Labels exist in Polish and English only; Polish is the default and the webhook summary is Polish only.
- Commits are attributed by author email alone. Commits made with an email that is not in `authorEmails` are not
  counted, and nothing outside the repositories of `owners` is counted except public pull requests.
- Open source numbers only include public pull requests found by GitHub search; contributions made by direct
  pushes to other projects are not seen.
- Repositories with more than 100 branches are paged with extra queries; very large histories take many API
  calls on the first run, which is what `seed` is for.
- The `discord` card needs an external bot that writes the profile file; ghapi does not connect to the Discord
  gateway itself.
- Snapshots are rebuilt by the collector only. Data is as fresh as the last collector run.

## License

MIT, see [LICENSE](LICENSE). The badge images in `assets/badges` come from the discord-badges collection by
Dominik K., also under the MIT license, see `assets/badges/LICENSE-mezotv-discord-badges`. Discord badge designs are
trademarks of Discord Inc.
