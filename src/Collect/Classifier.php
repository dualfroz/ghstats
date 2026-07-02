<?php

declare(strict_types=1);

namespace Ghapi\Collect;

final class Classifier
{
    public const KINDS = ['code', 'test', 'docs', 'config', 'lock', 'data', 'asset', 'other'];

    private const EXTENSIONS = [
        'ts' => 'TypeScript', 'tsx' => 'TypeScript', 'mts' => 'TypeScript', 'cts' => 'TypeScript',
        'js' => 'JavaScript', 'jsx' => 'JavaScript', 'mjs' => 'JavaScript', 'cjs' => 'JavaScript',
        'rs' => 'Rust',
        'php' => 'PHP',
        'go' => 'Go',
        'cs' => 'C#',
        'py' => 'Python',
        'ex' => 'Elixir', 'exs' => 'Elixir',
        'kt' => 'Kotlin', 'kts' => 'Kotlin',
        'java' => 'Java',
        'swift' => 'Swift',
        'rb' => 'Ruby',
        'cpp' => 'C++', 'cc' => 'C++', 'cxx' => 'C++', 'hpp' => 'C++',
        'c' => 'C', 'h' => 'C',
        'html' => 'HTML', 'htm' => 'HTML',
        'css' => 'CSS',
        'scss' => 'Sass', 'sass' => 'Sass',
        'dart' => 'Dart',
        'vue' => 'Vue',
        'svelte' => 'Svelte',
        'sql' => 'SQL',
        'sh' => 'Shell', 'bash' => 'Shell',
        'hs' => 'Haskell', 'lhs' => 'Haskell',
        'lua' => 'Lua',
        'zig' => 'Zig',
        'fs' => 'F#', 'fsx' => 'F#', 'fsi' => 'F#',
        'scala' => 'Scala',
        'sol' => 'Solidity',
        's' => 'Assembly', 'asm' => 'Assembly',
    ];

    private const LOCKFILES = [
        'package-lock.json', 'npm-shrinkwrap.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lockb', 'bun.lock',
        'composer.lock', 'cargo.lock', 'go.sum', 'go.work.sum', 'poetry.lock', 'pipfile.lock', 'uv.lock',
        'gemfile.lock', 'mix.lock', 'pubspec.lock', 'flake.lock', 'package.resolved', 'packages.lock.json',
        'gradle.lockfile', 'podfile.lock', 'deno.lock',
    ];

    private const DOCS = ['md', 'mdx', 'rst', 'adoc', 'txt', 'org'];
    private const CONFIG = [
        'json', 'json5', 'jsonc', 'yaml', 'yml', 'toml', 'ini', 'cfg', 'conf', 'xml', 'properties', 'env',
        'plist', 'gradle', 'csproj', 'fsproj', 'sln', 'props', 'targets', 'editorconfig', 'lock', 'nix', 'mod',
        'dockerignore', 'gitignore', 'gitattributes', 'npmrc', 'nvmrc', 'babelrc', 'eslintrc', 'prettierrc',
    ];
    private const CONFIG_NAMES = ['dockerfile', 'makefile', 'procfile', 'justfile', 'caddyfile', 'vagrantfile', 'gemfile', 'podfile', 'license', 'codeowners'];
    private const DATA = ['csv', 'tsv', 'parquet', 'ndjson', 'jsonl', 'db', 'sqlite', 'dat', 'bin', 'pb'];
    private const ASSETS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff', 'psd', 'ai', 'mp3', 'wav', 'ogg',
        'flac', 'mp4', 'webm', 'mov', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'glb', 'gltf', 'fbx', 'obj', 'blend',
        'pdf', 'zip', 'gz', 'tar', 'jar', 'dll', 'so', 'exe', 'aseprite', 'tres', 'tscn', 'unity', 'prefab', 'mat', 'asset', 'meta',
    ];

    public static function language(string $path): ?string
    {
        $extension = self::extension($path);
        return $extension === null ? null : (self::EXTENSIONS[$extension] ?? null);
    }

    public static function kind(string $path): string
    {
        $name = strtolower(basename($path));
        if (in_array($name, self::LOCKFILES, true)) {
            return 'lock';
        }
        $extension = self::extension($path);
        $language = $extension === null ? null : (self::EXTENSIONS[$extension] ?? null);
        if ($language !== null) {
            return self::isTestPath($path) ? 'test' : 'code';
        }
        if ($extension === null) {
            return in_array($name, self::CONFIG_NAMES, true) || str_starts_with($name, '.') ? 'config' : 'other';
        }
        if (in_array($extension, self::DOCS, true)) {
            return $name === 'requirements.txt' ? 'config' : 'docs';
        }
        if (in_array($extension, self::CONFIG, true) || str_starts_with($name, 'dockerfile')) {
            return 'config';
        }
        if (in_array($extension, self::DATA, true)) {
            return 'data';
        }
        if (in_array($extension, self::ASSETS, true)) {
            return 'asset';
        }
        return 'other';
    }

    public static function isTestPath(string $path): bool
    {
        $lower = strtolower(str_replace('\\', '/', $path));
        if (preg_match('#(^|/)(tests?|__tests__|__mocks__|specs?|testing|e2e|integration-tests|testdata|test-utils|fixtures)(/|$)#', $lower)) {
            return true;
        }
        $name = basename($lower);
        if (preg_match('#(^test_.*\.py$)|(_test\.(go|py|rs|exs|dart|c|cpp|zig)$)|(\.(test|spec|e2e)\.[a-z]+$)|(_spec\.rb$)#', $name)) {
            return true;
        }
        return (bool) preg_match('#(^|[a-z0-9])(Tests?|Spec)\.(java|kt|cs|php|swift|scala|fs)$#', basename(str_replace('\\', '/', $path)));
    }

    public static function extension(string $path): ?string
    {
        $name = basename(str_replace('\\', '/', $path));
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === strlen($name) - 1) {
            return null;
        }
        if ($dot === 0) {
            return strtolower(substr($name, 1));
        }
        return strtolower(substr($name, $dot + 1));
    }

    public static function normalizeNumstatPath(string $path): string
    {
        $path = trim($path);
        if (str_contains($path, '=>')) {
            if (preg_match('#^(.*)\{(.*) => (.*)\}(.*)$#', $path, $match)) {
                return str_replace('//', '/', $match[1] . $match[3] . $match[4]);
            }
            $parts = explode('=>', $path);
            return trim(end($parts));
        }
        return $path;
    }
}
