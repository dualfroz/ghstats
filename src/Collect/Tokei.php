<?php

declare(strict_types=1);

namespace Ghapi\Collect;

use Ghapi\Support\Process;

final class Tokei
{
    public function __construct(
        private readonly string $binary,
        private readonly array $aliases,
        private readonly array $denylist,
    ) {
    }

    public function available(): bool
    {
        return Process::run([$this->binary, '--version'], null, 15)->ok();
    }

    public function scan(string $root): ?array
    {
        $result = Process::run([$this->binary, $root, '--output', 'json', '--files'], null, 600);
        if (!$result->ok()) {
            return null;
        }
        $report = json_decode($result->stdout, true);
        if (!is_array($report)) {
            return null;
        }
        $languages = [];
        foreach ($report as $language => $node) {
            if ($language === 'Total' || !is_array($node)) {
                continue;
            }
            $canonical = $this->canonical((string) $language);
            if ($canonical === null) {
                continue;
            }
            $code = (int) ($node['code'] ?? 0);
            $bytes = 0;
            $files = 0;
            foreach ($node['reports'] ?? [] as $file) {
                $size = @filesize((string) ($file['name'] ?? ''));
                if ($size !== false) {
                    $bytes += $size;
                    $files++;
                }
            }
            if ($code <= 0 && $bytes <= 0) {
                continue;
            }
            $languages[$canonical] ??= ['code' => 0, 'bytes' => 0, 'files' => 0];
            $languages[$canonical]['code'] += $code;
            $languages[$canonical]['bytes'] += $bytes;
            $languages[$canonical]['files'] += $files;
        }
        ksort($languages, SORT_STRING | SORT_FLAG_CASE);
        return $languages;
    }

    public function canonical(string $language): ?string
    {
        $name = $this->aliases[$language] ?? $language;
        foreach ($this->denylist as $denied) {
            if (strcasecmp((string) $denied, $name) === 0) {
                return null;
            }
        }
        return $name;
    }
}
