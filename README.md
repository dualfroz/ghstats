# ghstats

Self-hosted GitHub stats service that renders SVG cards for a profile README.
It replaces github-readme-stats with an in-house solution so that a Personal
Access Token and private repository data never leave the VPS.

Target deployment: the main VPS behind nginx at `https://ghapi.dualfroz.com`.

## Why two parts

The core anti-DDoS design is a hard split between the part that holds secrets and
touches git/GitHub, and the part that is exposed to the world:

- The **collector** has the secrets. It enumerates repos, clones/pulls them,
  runs `tokei`, computes every number, and writes a single `snapshot.json`. It
  runs on a timer on the host, never on a request.
- The **server** is exposed. It only reads `snapshot.json`, keeps it in memory,
  pre-renders the SVG cards, and serves them. It never runs git, never calls
  GitHub, and holds no secrets. Serving a card is O(1) - a string already in
  memory - so a flood of requests cannot make it do real work.

## Modules

```
ghstats/
  ghstats-common/      shared snapshot model, linguist colours, JSON mapper
  ghstats-collector/   CLI: enumerate -> clone/pull -> compute -> snapshot.json
  ghstats-server/      Spring Boot: snapshot.json -> in-memory -> SVG cards
  Dockerfile           server image (multi-stage, non-root, port 14330)
  Dockerfile.collector optional collector image (git + gh + tokei baked in)
  docker-compose.yml   server service, read-only data mount, loopback publish
  deploy/              systemd units, nginx site, collector override example
```

## Build

Java 21 + Spring Boot 3, Maven via the bundled wrapper (no local `mvn` needed).

```
./mvnw -B package
```

This builds `ghstats-collector/target/ghstats-collector.jar` and
`ghstats-server/target/ghstats-server.jar` (both executable fat jars).

Note: the compiler release is 21. If a build host only has a JDK 17 toolchain,
you can verify the source compiles with
`./mvnw -Djava.version=17 -Dmaven.compiler.release=17 package`, but production
artifacts should be built with JDK 21 (the Docker images use
`maven:3.9-eclipse-temurin-21`, which is correct out of the box).

## Run locally

Server against a snapshot on disk:

```
java -jar ghstats-server/target/ghstats-server.jar \
  --ghstats.server.snapshotPath=/path/to/snapshot.json \
  --server.port=14330
# then open http://127.0.0.1:14330/stats
```

Collector (needs `gh` logged in, `git`, and `tokei` on PATH):

```
java -jar ghstats-collector/target/ghstats-collector.jar \
  --ghstats.collector.reposRoot=/srv/ghstats/repos \
  --ghstats.collector.snapshotPath=/srv/ghstats/data/snapshot.json
```

## Endpoints

All card endpoints return `image/svg+xml`, send `Cache-Control: public,
max-age=1800` and a weak `ETag`, honour `If-None-Match` (304), and are rate
limited per client IP (default 60/min, `429` with `Retry-After` on overflow).

| Endpoint         | Card                                                     |
|------------------|----------------------------------------------------------|
| `/stats`         | Overview: own commits, repos, stars, languages, active days, LOC, lines added/removed |
| `/top-langs`     | Pie chart of languages by lines, top 12 + Other, two-column legend with % |
| `/streak`        | Total contributions, current streak (with dates), longest streak |
| `/activity`      | Commit activity line/area chart (last 52 weeks; `?range=all` = per-year) |
| `/heatmap`       | 53x7 contribution heatmap (`?range=all` = stacked per-year blocks, full history) |
| `/loc`           | Lines of code total plus a per-language breakdown        |
| `/by-year`       | Vertical bars of commits per year (multi-year history)   |
| `/top-repos`     | Horizontal bars of top repositories by own commits       |
| `/punchcard`     | When-I-code grid: weekday x hour bubbles                  |
| `/achievements`  | Badge grid computed from the snapshot                     |
| `/healthz`       | JSON `{status, generated_at, age_seconds}` (not rate limited) |

Query params:

- `theme=tokyonight` (default) or `theme=light`. Unknown themes fall back to the
  default.
- `hide_border` (`?hide_border=true`) drops the card outline.
- `range=all` on `/activity` and `/heatmap` renders the all-time view (per-year
  bars / stacked per-year heatmap blocks); the default (`range=year`) renders the
  last ~52 weeks.

Example URLs:

```
https://ghapi.dualfroz.com/stats
https://ghapi.dualfroz.com/top-langs?theme=tokyonight
https://ghapi.dualfroz.com/streak
https://ghapi.dualfroz.com/activity?range=all
https://ghapi.dualfroz.com/heatmap?range=all
https://ghapi.dualfroz.com/by-year
https://ghapi.dualfroz.com/top-repos
https://ghapi.dualfroz.com/punchcard
https://ghapi.dualfroz.com/achievements
https://ghapi.dualfroz.com/loc?hide_border=true
```

## Embed in the profile README

`/stats`, `/top-langs`, `/loc` and `/by-year` share the same 495x216 outer size,
so pair them two-per-row. `/heatmap`, `/punchcard` and `/heatmap?range=all` are
full width. `/top-repos` and `/achievements` are variable-height, best full width.

```markdown
<!-- Two-column row: matched 495x216 cards -->
<p align="center">
  <img src="https://ghapi.dualfroz.com/stats" height="180"/>
  <img src="https://ghapi.dualfroz.com/top-langs" height="180"/>
</p>
<p align="center">
  <img src="https://ghapi.dualfroz.com/loc" height="180"/>
  <img src="https://ghapi.dualfroz.com/by-year" height="180"/>
</p>

<!-- Full width -->
![Streak](https://ghapi.dualfroz.com/streak)
![Heatmap](https://ghapi.dualfroz.com/heatmap)
![When I code](https://ghapi.dualfroz.com/punchcard)
![Top repositories](https://ghapi.dualfroz.com/top-repos)
![Achievements](https://ghapi.dualfroz.com/achievements)

<!-- The multi-year flex: full history GitHub will not show -->
![All-time heatmap](https://ghapi.dualfroz.com/heatmap?range=all)
```

## snapshot.json contract

Written atomically (temp file + rename) by the collector, read by the server.

```json
{
  "generated_at": "2026-08-25T18:00:00Z",
  "identity": { "login": "dualfroz", "name": "DualFroz" },
  "totals": { "own_commits": 0, "repos_own": 0, "repos_scanned": 0, "stars": 0,
              "languages": 0, "lines_of_code": 0, "active_days": 0,
              "followers": 0, "public_repos": 0,
              "lines_added": 0, "lines_removed": 0 },
  "streak": { "current": 0, "longest": 0, "current_start": null, "current_end": null },
  "languages_by_lines": [ { "name": "TypeScript", "lines": 0, "share": 0.0, "color": "#3178c6" } ],
  "calendar": [ { "date": "2026-08-25", "count": 0 } ],
  "calendar_all": [ { "date": "2018-03-04", "count": 0 } ],
  "by_year": [ { "year": "2021", "count": 0 } ],
  "top_repos": [ { "name": "owner/repo", "own_commits": 0 } ],
  "punch_card": [ { "weekday": 1, "hour": 14, "count": 0 } ]
}
```

Totals (`own_commits`, `active_days`, `streak`, `lines_added`, `lines_removed`)
are computed from the full git history, not just the last year - GitHub hides
commits made before an account existed and we deliberately show them. `calendar`
is the last ~371 days for the heatmap and the 52-week activity view; `calendar_all`
is every day with at least one commit across the whole history (for
`/heatmap?range=all`); `by_year` is the all-time per-year series; `punch_card`
holds weekday (1-7 Mon-Sun) x hour (0-23) commit counts. `lines_added` and
`lines_removed` come from `git log --numstat` over his commits across all history.

## What counts as "his" work

- Commits are attributed by author email only, from `author_emails`:
  `me@dualfroz.com`, `me@dualfroz.com`,
  `284081405+dualfroz@users.noreply.github.com`.
- A repo counts towards stats (commits, LOC, pie, stars, top repos) only if it
  has at least one commit by those emails. Cloned upstream forks (e.g.
  pterodactyl) with zero of his commits are cloned but skipped in every number.
- `full_count_repos`: repos listed here have EVERY commit counted regardless of
  author (matched by bare name or `owner/name`). Use this for backdated/prank
  repos where the author email is unreliable.
- `baseline_commits`: a manual integer added to `totals.own_commits`. Use it to
  preserve a count for history you later remove from a repo. Keep it at 0 unless
  you specifically need the offset.

## Language filtering

The collector cleans up the raw tokei language set before writing the snapshot,
so the pie and LOC card show a real language breakdown:

- `language_aliases` merges variants into one language (default `Tsx` ->
  `TypeScript`, `Jsx` -> `JavaScript`), summing their lines.
- `language_denylist` drops non-code and data formats (default `JSON`, `JSON5`,
  `YAML`, `TOML`, `Markdown`, `Plain Text`, `Text`, `SVG`, `XML`, `INI`,
  `Dotenv`, `CSV`). Programming languages plus `HTML`/`CSS`/`SCSS`/`Sass`/`Less`
  are kept.

After merge and exclusion, `share` is normalised over the kept languages (sums
to 100%), `totals.languages` is the kept count, and `totals.lines_of_code` is the
sum of the kept languages, so the total always matches its own breakdown. The
server renders top 12 + `Other` from the kept list.

## Configuration

Collector (`ghstats.collector.*`, defaults in
`ghstats-collector/src/main/resources/application.yml`, override with
`/etc/ghstats/collector.yml`, see `deploy/collector.yml.example`):

| Key                       | Default                              | Meaning |
|---------------------------|--------------------------------------|---------|
| `owners`                  | dualfroz, VulCodeCom, Sinlege        | owners to enumerate |
| `authorEmails`            | (the three above)                    | emails that count as his |
| `fullCountRepos`          | []                                   | count all commits in these repos |
| `baselineCommits`         | 0                                    | manual offset on own_commits |
| `languageAliases`         | Tsx->TypeScript, Jsx->JavaScript     | merge language variants |
| `languageDenylist`        | JSON, YAML, Markdown, ...            | languages dropped from stats |
| `reposRoot`               | /srv/ghstats/repos                   | where clones live |
| `snapshotPath`            | /srv/ghstats/data/snapshot.json      | output file |
| `ghBin` / `gitBin` / `tokeiBin` | gh / git / tokei              | external binaries |
| `cloneTimeoutSeconds`     | 600                                  | per-repo clone timeout |
| `gitTimeoutSeconds`       | 120                                  | per-repo pull timeout |
| `numstatTimeoutSeconds`   | 300                                  | per-repo commit+numstat log timeout |
| `tokeiTimeoutSeconds`     | 120                                  | per-repo tokei timeout |
| `calendarDays`            | 371                                  | calendar window length |
| `topReposCount`           | 12                                   | entries in top_repos |
| `repoListLimit`           | 1000                                 | gh repo list --limit |

Server (`ghstats.server.*`,
`ghstats-server/src/main/resources/application.yml`):

| Key                    | Default                          | Meaning |
|------------------------|----------------------------------|---------|
| `snapshotPath`         | /srv/ghstats/data/snapshot.json  | file to read (mounted read-only) |
| `pollIntervalSeconds`  | 30                               | mtime poll for reload |
| `rateLimitPerMinute`   | 60                               | per-IP request budget |
| `cacheMaxAgeSeconds`   | 1800                             | Cache-Control max-age |
| `defaultTheme`         | tokyonight                       | theme when none is given |

Environment variables use the Spring relaxed-binding form, e.g.
`GHSTATS_SERVER_RATELIMITPERMINUTE=120` (see `docker-compose.yml`).

## Resilience

- Each external step (enumerate, clone, pull, log, tokei) has its own timeout;
  one bad repo is logged and skipped, it never aborts the run.
- Missing `tokei` is detected once up front - the run still produces a snapshot,
  just with empty LOC/languages.
- An empty repo or a repo with no matching commits is simply skipped in stats.
- If `snapshot.json` is missing or empty, the server serves a "no data" card
  instead of a 500, and `/healthz` reports `no_data`/`empty`.
- The snapshot write is atomic, so the server never reads a half-written file.

## Deploy runbook (main VPS)

Prerequisites on the host: `git`, `gh` logged in as dualfroz (`gh auth status`),
`tokei` on PATH, Java 21, Docker + docker compose.

1. Layout and user

```
sudo useradd --system --home /srv/ghstats --shell /usr/sbin/nologin ghstats
sudo mkdir -p /srv/ghstats/repos /srv/ghstats/data /opt/ghstats /etc/ghstats
sudo chown -R ghstats:ghstats /srv/ghstats
```

`gh` auth: the collector runs as `ghstats`, so that user needs a logged-in gh.
Either `sudo -u ghstats gh auth login`, or set `GH_TOKEN` in the unit, or point
the unit at an existing logged-in user - whichever matches how the host is set
up. Confirm with `sudo -u ghstats gh auth status`.

2. Install tokei (if absent)

```
curl -fsSL https://github.com/XAMPPRocky/tokei/releases/download/v12.1.2/tokei-x86_64-unknown-linux-gnu.tar.gz \
  | sudo tar -xz -C /usr/local/bin tokei
```

3. Collector: jar, config, systemd timer

```
sudo cp ghstats-collector/target/ghstats-collector.jar /opt/ghstats/
sudo cp deploy/collector.yml.example /etc/ghstats/collector.yml   # edit as needed
sudo cp deploy/ghstats-collector.service deploy/ghstats-collector.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ghstats-collector.timer
sudo systemctl start ghstats-collector.service     # first run now
journalctl -u ghstats-collector.service -f          # watch it
```

The first run clones everything (can take a while); later runs `git pull
--ff-only` and recompute. The timer fires every 20 minutes.

4. Server: docker compose (publishes only to loopback)

```
docker compose up -d --build server
curl -s http://127.0.0.1:14330/healthz
```

The compose file mounts `/srv/ghstats/data` read-only and publishes
`127.0.0.1:14330:14330`, so the server is reachable only via localhost / nginx.

5. nginx + TLS

```
sudo cp deploy/nginx-ghapi.dualfroz.com.conf /etc/nginx/sites-available/ghapi.dualfroz.com
sudo ln -s /etc/nginx/sites-available/ghapi.dualfroz.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d ghapi.dualfroz.com          # HTTP-01, subdomain already resolves
```

6. Verify end to end

```
curl -s https://ghapi.dualfroz.com/healthz
curl -s https://ghapi.dualfroz.com/stats | head -c 60
```

## Themes

`tokyonight` (default): background `#1a1b27`, text `#a9b1d6`, accent `#7aa2f7`,
icons `#bb9af7`, border `#2f3549`. `light` is a GitHub-light variant. Each card
paints its own background, so it reads correctly in both GitHub light and dark.
