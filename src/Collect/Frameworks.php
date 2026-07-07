<?php

declare(strict_types=1);

namespace Ghapi\Collect;

final class Frameworks
{
    private const SKIP_DIRS = [
        'node_modules', 'vendor', '.git', 'dist', 'build', 'target', '.venv', 'venv', 'env', 'Pods', '.next', '.nuxt',
        'out', 'bin', 'obj', '.gradle', '.idea', '.vscode', 'coverage', '__pycache__', '.dart_tool', 'deps', '_build',
        'third_party', 'external', 'Library', 'Temp', '.svelte-kit', '.turbo', '.cache',
    ];

    private const MANIFESTS = [
        'package.json', 'composer.json', 'go.mod', 'cargo.toml', 'requirements.txt', 'pyproject.toml', 'pipfile',
        'pom.xml', 'build.gradle', 'build.gradle.kts', 'mix.exs', 'pubspec.yaml', 'gemfile', 'package.swift',
        'deno.json', 'build.zig.zon',
    ];

    private const CATALOG = [
        'npm' => [
            'react' => 'React', 'react-dom' => 'React', 'next' => 'Next.js', 'vue' => 'Vue', 'nuxt' => 'Nuxt',
            'svelte' => 'Svelte', '@sveltejs/kit' => 'SvelteKit', 'astro' => 'Astro', '@angular/core' => 'Angular',
            'solid-js' => 'Solid', 'preact' => 'Preact', 'express' => 'Express', 'fastify' => 'Fastify',
            '@nestjs/core' => 'NestJS', 'hono' => 'Hono', 'koa' => 'Koa', 'elysia' => 'Elysia', 'electron' => 'Electron',
            'react-native' => 'React Native', 'expo' => 'Expo', '@tauri-apps/api' => 'Tauri', 'vite' => 'Vite',
            'webpack' => 'webpack', 'tailwindcss' => 'Tailwind CSS', 'prisma' => 'Prisma', '@prisma/client' => 'Prisma',
            'drizzle-orm' => 'Drizzle', 'typeorm' => 'TypeORM', 'sequelize' => 'Sequelize', 'mongoose' => 'Mongoose',
            'three' => 'Three.js', 'gsap' => 'GSAP', 'framer-motion' => 'Framer Motion', 'motion' => 'Motion',
            'socket.io' => 'Socket.IO', 'discord.js' => 'discord.js', 'zod' => 'Zod', 'redux' => 'Redux',
            '@reduxjs/toolkit' => 'Redux', 'zustand' => 'Zustand', '@tanstack/react-query' => 'TanStack Query',
            'jest' => 'Jest', 'vitest' => 'Vitest', '@playwright/test' => 'Playwright', 'playwright' => 'Playwright',
            'cypress' => 'Cypress', 'graphql' => 'GraphQL', '@apollo/client' => 'Apollo', 'trpc' => 'tRPC',
            '@trpc/server' => 'tRPC', 'pixi.js' => 'PixiJS', 'phaser' => 'Phaser', 'd3' => 'D3', 'chart.js' => 'Chart.js',
            'i18next' => 'i18next', 'storybook' => 'Storybook', 'bullmq' => 'BullMQ', 'ioredis' => 'Redis', 'redis' => 'Redis',
            'pg' => 'PostgreSQL', 'mysql2' => 'MySQL', 'better-sqlite3' => 'SQLite', 'stripe' => 'Stripe',
        ],
        'composer' => [
            'laravel/framework' => 'Laravel', 'symfony/framework-bundle' => 'Symfony', 'symfony/http-kernel' => 'Symfony',
            'slim/slim' => 'Slim', 'livewire/livewire' => 'Livewire', 'filament/filament' => 'Filament',
            'phpunit/phpunit' => 'PHPUnit', 'pestphp/pest' => 'Pest', 'doctrine/orm' => 'Doctrine',
            'guzzlehttp/guzzle' => 'Guzzle', 'inertiajs/inertia-laravel' => 'Inertia', 'pterodactyl/panel' => 'Pterodactyl',
        ],
        'go' => [
            'github.com/gin-gonic/gin' => 'Gin', 'github.com/labstack/echo' => 'Echo', 'github.com/gofiber/fiber' => 'Fiber',
            'github.com/go-chi/chi' => 'chi', 'gorm.io/gorm' => 'GORM', 'github.com/spf13/cobra' => 'Cobra',
            'github.com/charmbracelet/bubbletea' => 'Bubble Tea', 'github.com/docker/docker' => 'Docker SDK',
            'github.com/gorilla/websocket' => 'Gorilla WebSocket', 'github.com/jackc/pgx' => 'PostgreSQL',
            'github.com/redis/go-redis' => 'Redis', 'google.golang.org/grpc' => 'gRPC', 'github.com/yuin/gopher-lua' => 'gopher-lua',
            'github.com/hajimehoshi/ebiten' => 'Ebitengine', 'github.com/bwmarrin/discordgo' => 'discordgo',
        ],
        'cargo' => [
            'tokio' => 'Tokio', 'axum' => 'Axum', 'actix-web' => 'Actix Web', 'rocket' => 'Rocket', 'warp' => 'warp',
            'serde' => 'Serde', 'tauri' => 'Tauri', 'bevy' => 'Bevy', 'ratatui' => 'Ratatui', 'sqlx' => 'SQLx',
            'diesel' => 'Diesel', 'clap' => 'clap', 'leptos' => 'Leptos', 'wgpu' => 'wgpu', 'egui' => 'egui',
            'reqwest' => 'reqwest', 'tonic' => 'tonic', 'serenity' => 'Serenity', 'wasm-bindgen' => 'wasm-bindgen',
            'iced' => 'iced', 'windows' => 'windows-rs',
        ],
        'python' => [
            'django' => 'Django', 'flask' => 'Flask', 'fastapi' => 'FastAPI', 'pydantic' => 'Pydantic',
            'sqlalchemy' => 'SQLAlchemy', 'discord.py' => 'discord.py', 'py-cord' => 'Pycord', 'pytest' => 'pytest',
            'numpy' => 'NumPy', 'pandas' => 'pandas', 'torch' => 'PyTorch', 'tensorflow' => 'TensorFlow',
            'playwright' => 'Playwright', 'aiohttp' => 'aiohttp', 'celery' => 'Celery', 'scrapy' => 'Scrapy',
            'pygame' => 'pygame', 'textual' => 'Textual', 'typer' => 'Typer', 'click' => 'Click', 'httpx' => 'HTTPX',
        ],
        'jvm' => [
            'org.springframework.boot' => 'Spring Boot', 'spring-boot' => 'Spring Boot', 'io.ktor' => 'Ktor',
            'androidx.compose' => 'Jetpack Compose', 'com.android.application' => 'Android', 'com.android.tools.build' => 'Android',
            'junit' => 'JUnit', 'org.jetbrains.kotlinx:kotlinx-coroutines' => 'Coroutines', 'io.papermc' => 'Paper',
            'org.spigotmc' => 'Spigot', 'net.dv8tion' => 'JDA', 'hibernate' => 'Hibernate',
        ],
        'dotnet' => [
            'Microsoft.AspNetCore' => 'ASP.NET Core', 'Microsoft.NET.Sdk.Web' => 'ASP.NET Core', 'Avalonia' => 'Avalonia',
            'Microsoft.Maui' => '.NET MAUI', 'Microsoft.EntityFrameworkCore' => 'Entity Framework Core', 'xunit' => 'xUnit',
            'NUnit' => 'NUnit', 'Discord.Net' => 'Discord.Net', 'EXILED' => 'EXILED', 'Newtonsoft.Json' => 'Json.NET',
            'MonoGame' => 'MonoGame', 'Serilog' => 'Serilog', 'Harmony' => 'Harmony', 'Lib.Harmony' => 'Harmony',
            'Microsoft.WindowsDesktop' => 'WPF', 'UseWPF' => 'WPF', 'UseWindowsForms' => 'Windows Forms',
        ],
        'elixir' => ['phoenix' => 'Phoenix', 'ecto' => 'Ecto', 'phoenix_live_view' => 'LiveView', 'oban' => 'Oban', 'nerves' => 'Nerves'],
        'dart' => ['flutter' => 'Flutter', 'flame' => 'Flame', 'riverpod' => 'Riverpod', 'bloc' => 'Bloc'],
        'ruby' => ['rails' => 'Rails', 'sinatra' => 'Sinatra', 'rspec' => 'RSpec'],
        'swift' => ['swift-nio' => 'SwiftNIO', 'vapor' => 'Vapor', 'swift-composable-architecture' => 'TCA'],
    ];

    public static function scan(string $root): array
    {
        $found = [];
        foreach (self::manifestFiles($root) as $file) {
            $relative = ltrim(substr($file, strlen($root)), '/');
            foreach (self::detectFile($file) as $name => $ecosystem) {
                $found[$name] ??= ['name' => $name, 'ecosystem' => $ecosystem, 'paths' => []];
                if (count($found[$name]['paths']) < 5) {
                    $found[$name]['paths'][] = $relative;
                }
            }
        }
        foreach (self::unityMarkers($root) as $name => $ecosystem) {
            $found[$name] ??= ['name' => $name, 'ecosystem' => $ecosystem, 'paths' => []];
        }
        ksort($found, SORT_STRING | SORT_FLAG_CASE);
        return array_values($found);
    }

    public static function detectFile(string $file): array
    {
        $name = strtolower(basename($file));
        $contents = @file_get_contents($file, false, null, 0, 2_000_000);
        if (!is_string($contents)) {
            return [];
        }
        if (str_ends_with($name, '.csproj') || str_ends_with($name, '.fsproj')) {
            return self::match('dotnet', self::dotnetDependencies($contents));
        }
        return match ($name) {
            'package.json', 'deno.json' => self::match('npm', self::npmDependencies($contents)),
            'composer.json' => self::match('composer', self::composerDependencies($contents)),
            'go.mod' => self::match('go', self::goDependencies($contents), true),
            'cargo.toml' => self::match('cargo', self::tomlDependencies($contents)),
            'requirements.txt', 'pipfile' => self::match('python', self::requirementDependencies($contents)),
            'pyproject.toml' => self::match('python', array_merge(self::requirementDependencies($contents), self::tomlDependencies($contents))),
            'pom.xml', 'build.gradle', 'build.gradle.kts' => self::match('jvm', [$contents], true),
            'mix.exs' => self::match('elixir', self::atomDependencies($contents)),
            'pubspec.yaml' => self::match('dart', self::yamlDependencies($contents)),
            'gemfile' => self::match('ruby', self::gemDependencies($contents)),
            'package.swift' => self::match('swift', [$contents], true),
            default => [],
        };
    }

    private static function manifestFiles(string $root): array
    {
        $files = [];
        $stack = [[$root, 0]];
        while ($stack !== [] && count($files) < 60) {
            [$dir, $depth] = array_pop($stack);
            $entries = @scandir($dir);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . '/' . $entry;
                if (is_link($path)) {
                    continue;
                }
                if (is_dir($path)) {
                    if ($depth < 4 && !in_array($entry, self::SKIP_DIRS, true)) {
                        $stack[] = [$path, $depth + 1];
                    }
                    continue;
                }
                $lower = strtolower($entry);
                if (in_array($lower, self::MANIFESTS, true) || str_ends_with($lower, '.csproj') || str_ends_with($lower, '.fsproj')) {
                    $files[] = $path;
                }
            }
        }
        sort($files);
        return $files;
    }

    private static function unityMarkers(string $root): array
    {
        $markers = [];
        if (is_file($root . '/ProjectSettings/ProjectVersion.txt')) {
            $markers['Unity'] = 'unity';
        }
        if (is_file($root . '/project.godot')) {
            $markers['Godot'] = 'godot';
        }
        if (is_file($root . '/Dockerfile') || is_file($root . '/docker-compose.yml') || is_file($root . '/compose.yaml') || is_file($root . '/docker-compose.yaml')) {
            $markers['Docker'] = 'tooling';
        }
        return $markers;
    }

    private static function match(string $ecosystem, array $dependencies, bool $substring = false): array
    {
        $found = [];
        foreach (self::CATALOG[$ecosystem] as $needle => $label) {
            foreach ($dependencies as $dependency) {
                $hit = $substring
                    ? str_contains((string) $dependency, $needle)
                    : strcasecmp((string) $dependency, $needle) === 0;
                if ($hit) {
                    $found[$label] = $ecosystem;
                    break;
                }
            }
        }
        return $found;
    }

    private static function npmDependencies(string $contents): array
    {
        $json = json_decode($contents, true);
        if (!is_array($json)) {
            return [];
        }
        $names = [];
        foreach (['dependencies', 'devDependencies', 'peerDependencies', 'imports'] as $section) {
            if (isset($json[$section]) && is_array($json[$section])) {
                foreach (array_keys($json[$section]) as $key) {
                    $names[] = (string) $key;
                }
            }
        }
        return $names;
    }

    private static function composerDependencies(string $contents): array
    {
        $json = json_decode($contents, true);
        if (!is_array($json)) {
            return [];
        }
        $names = [];
        foreach (['require', 'require-dev'] as $section) {
            if (isset($json[$section]) && is_array($json[$section])) {
                $names = array_merge($names, array_map('strval', array_keys($json[$section])));
            }
        }
        if (isset($json['name'])) {
            $names[] = (string) $json['name'];
        }
        return $names;
    }

    private static function goDependencies(string $contents): array
    {
        preg_match_all('#^\s*(?:require\s+)?([a-z0-9.\-]+\.[a-z]{2,}/[^\s]+)\s+v#mi', $contents, $matches);
        return $matches[1];
    }

    private static function tomlDependencies(string $contents): array
    {
        $names = [];
        $inDependencies = false;
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '[')) {
                $inDependencies = (bool) preg_match('#dependencies#i', $trimmed);
                if (preg_match('#dependencies\.([A-Za-z0-9_\-]+)\]#', $trimmed, $match)) {
                    $names[] = $match[1];
                }
                continue;
            }
            if ($inDependencies && preg_match('#^([A-Za-z0-9_\-."]+)\s*=#', $trimmed, $match)) {
                $names[] = trim($match[1], '"');
            }
        }
        return $names;
    }

    private static function requirementDependencies(string $contents): array
    {
        preg_match_all('#^\s*["\']?([A-Za-z0-9_.\-]+)(?:\[[^\]]*\])?\s*(?:[<>=!~;,"\']|$)#m', $contents, $matches);
        return array_map('strtolower', $matches[1]);
    }

    private static function atomDependencies(string $contents): array
    {
        preg_match_all('#\{:\s*([a-z0-9_]+)\s*,#', $contents, $matches);
        return $matches[1];
    }

    private static function yamlDependencies(string $contents): array
    {
        preg_match_all('#^\s{1,4}([a-z0-9_]+):#m', $contents, $matches);
        return $matches[1];
    }

    private static function gemDependencies(string $contents): array
    {
        preg_match_all('#^\s*gem\s+["\']([^"\']+)#m', $contents, $matches);
        return $matches[1];
    }

    private static function dotnetDependencies(string $contents): array
    {
        preg_match_all('#Include="([^"]+)"#', $contents, $matches);
        $names = $matches[1];
        if (preg_match('#Sdk="([^"]+)"#', $contents, $sdk)) {
            $names[] = $sdk[1];
        }
        foreach (['UseWPF', 'UseWindowsForms'] as $flag) {
            if (preg_match("#<{$flag}>true</{$flag}>#i", $contents)) {
                $names[] = $flag;
            }
        }
        $expanded = [];
        foreach ($names as $name) {
            $expanded[] = $name;
            $parts = explode('.', $name);
            for ($i = count($parts) - 1; $i >= 1; $i--) {
                $expanded[] = implode('.', array_slice($parts, 0, $i));
            }
        }
        return $expanded;
    }
}
