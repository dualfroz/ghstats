# ghapi

Self-hosted GitHub statistics API behind `https://ghapi.dualfroz.com`. It serves raw JSON and SVG cards for
the dualfroz.com site, vulcode.com and the GitHub profile READMEs, plus a Discord app that answers the same
questions from any chat.

Plain PHP 8.2, no framework and no extensions beyond the default build with `openssl` and `sodium`.

## How it works

Two halves that never share secrets:

- **Collector** (`bin/ghapi collect`, systemd timer, runs as `ghapi`). Holds the GitHub token. Keeps a small
  per-repository state in `/srv/ghapi/state` and writes ready-made snapshots to `/srv/ghapi/snapshot`.
- **API** (`public/index.php` on PHP-FPM). Reads snapshots only. It never calls GitHub, so a flood of requests
  costs a file read.

No repository is kept on disk. The collector talks to the GitHub API:

1. GraphQL lists every repository of the configured owners with its branches, heads, stars and languages.
2. Unchanged branch heads cost nothing. A moved head is diffed with the compare API (`old...new`), a new
   branch against the default branch. That catches commits with old dates (backdated or pushed late), which a
   date-based query would miss.
3. A compare that reports the old head as behind (force push, rewritten history) triggers a full walk of the
   repository and commits that are no longer reachable are dropped.
4. Own commits are matched by author email and merge commits are skipped. Each new commit gets its file list
   from the commit API once, so language, test and file-kind numbers are exact line counts, the same as
   `git log --numstat`.
5. When the default branch moves, its tarball is unpacked to a temporary directory for `tokei` (living code,
   characters) and manifest scanning (frameworks), then deleted.
6. Public pull requests of the login feed the open source numbers.

The timer runs hourly. A full refresh happens once every `refreshHours` (24). Runs in between only finish
commit details that did not fit into the rate limit. After every full refresh a summary goes to the Discord
webhook, if one is configured.

### First run from existing clones

`bin/ghapi seed /srv/ghstats/repos` reads `git log --all --numstat` and `tokei` from clones laid out as
`<owner>/<repo>`. The first `collect` then only fetches what the clones did not have, instead of one API call
per historical commit. The clones can be deleted afterwards.

## Endpoints

Everything under `/v2/public` is open, has CORS enabled and never contains private repository names. The same
paths without `public/` need `Authorization: Bearer <key>` and add repository names and per-repository data.

Common query parameters: `owner=<account>` narrows to one account or organization, `lang=en|pl` for labels,
`pretty=1`. All responses send an `ETag` and answer `If-None-Match` with 304.

| Path | What |
|------|------|
| `/v2`, `/v2/public` | index of endpoints |
| `/v2/health` | snapshot age, pending commit details |
| `summary` | totals, streak, top languages and frameworks, tests, commit size, open source |
| `commits?group=year\|month\|week\|day\|hour\|weekday\|punchcard&range=all\|365d\|90d\|30d&from=&to=` | commit counts; hour and weekday use the author's local time |
| `commits/hours`, `commits/weekdays`, `commits/punchcard` | shortcuts |
| `commits/size` | distribution, median, p90, p99, largest commits, clean-up commits (more removed than added) |
| `streak` | current and longest streak with dates |
| `loc?group=&range=` | lines added and removed per period, totals, living code, file kinds |
| `languages?limit=` | lines added and removed, share, first and last year, living code, bytes |
| `frameworks?ecosystem=` | frameworks and tools found in manifests, number of repositories |
| `tests` | lines in test files against other source, per language (and per repository when private) |
| `compare?period=week\|month` | this period so far against the previous one, full and to the same day |
| `opensource?limit=` | pull requests to public projects, contributed projects with stars, own public repositories |
| `lifetime` | repositories active, maintained, dormant, inactive, archived; lifetime days |
| `repos?status=&visibility=&language=&sort=commits\|lines\|recent\|stars\|lifetime\|name` | repository list (public ones only under `/v2/public`) |
| `repos/{owner}/{name}` | one repository with monthly series |
| `meta` | private only: collector run, rate limits, errors |
| `cards/{name}.svg` | `stats`, `top-langs`, `streak`, `activity`, `heatmap`, `loc`, `by-year`, `top-repos`, `punchcard`, `achievements`, `opensource`, `frameworks`, `hours`, `discord`; `theme=tokyonight\|light`, `hide_border`, `range=all`, `name=` |

`POST /v2/discord/interactions` is the Discord interactions endpoint.

### Compatibility

The v1 paths keep working: `/data/stats` (same JSON shape as before), `/healthz`, `/stats`, `/top-langs`,
`/streak`, `/activity`, `/heatmap`, `/loc`, `/by-year`, `/top-repos`, `/punchcard`, `/achievements`, `/discord`.
The public `top-repos` card lists open source contributions, because repository names of private work are no
longer published.

### What counts

- Commits: author email in `authorEmails`, merges excluded, every branch. Repositories in `fullCountRepos`
  count every author. Forks never count.
- `linesAdded` / `linesRemoved`: every file of those commits. `codeLinesAdded`: files with a known programming
  language extension. `codeLinesLive` and `charsOfCode`: `tokei` on the current default branch.
- Tests: files under `tests/`, `__tests__/`, `spec/`, `e2e/` and names like `*_test.go`, `*.spec.ts`,
  `FooTest.java`.
- Open source `contributed`: merged pull requests to public repositories outside the configured owners.
  `targeted` also counts open and closed ones. `ownPublic`: public, non-fork repositories of the owners.

## Discord app

A user-installable app, so it works in DMs, group chats and every server without adding a bot. Slash commands:
`/help /summary /loc /commits /langs /oss /repo /compare /frameworks /sizes /tests`, each with `hidden` to
answer only to you. The owner (`discordUserId`) gets private data, anyone else the public view. Replies follow
the Discord client language (Polish or English).

Setup in the Developer Portal:

1. Installation: tick only **User Install**, install link **Discord Provided Link**, scopes `applications.commands`.
2. Put the application ID and public key into `/etc/ghapi/config.json`.
3. General Information: Interactions Endpoint URL `https://ghapi.dualfroz.com/v2/discord/interactions`.
4. Bot token into `/etc/ghapi/discord-bot-token` (root only), then `bin/ghapi discord:register`.

## Configuration

`/etc/ghapi/config.json` (readable by PHP-FPM and the collector) and `/etc/ghapi/collector.json` (collector
only, holds the webhook). Examples in `deploy/`. The GitHub token comes from `GH_TOKEN` in
`/etc/ghapi/github.env`. API keys are stored as SHA-256 hashes: `bin/ghapi key <label>` prints a new key and
the config entry.

## Deploy

```
useradd --system --home /srv/ghapi --shell /usr/sbin/nologin ghapi
install -d -o ghapi -g ghapi -m 0750 /srv/ghapi/state /srv/ghapi/tmp
install -d -o ghapi -g ghapi -m 0755 /srv/ghapi /srv/ghapi/snapshot
install -d -o www-data -g www-data -m 0750 /srv/ghapi/cache
cp deploy/ghapi-collector.{service,timer} /etc/systemd/system/
cp deploy/ghapi-limits.conf /etc/nginx/conf.d/
cp deploy/nginx-ghapi.dualfroz.com.conf /etc/nginx/sites-available/ghapi.dualfroz.com
systemctl daemon-reload && systemctl enable --now ghapi-collector.timer
nginx -t && systemctl reload nginx
```

## Tests

```
php tests/run.php
```
