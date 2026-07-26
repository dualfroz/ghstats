<?php

declare(strict_types=1);

namespace Ghapi\Support;

final class LanguageColors
{
    public const UNKNOWN = '#8b8b8b';

    private const COLORS = [
        'TypeScript' => '#3178c6', 'JavaScript' => '#f1e05a', 'Rust' => '#dea584', 'Go' => '#00ADD8',
        'Python' => '#3572A5', 'PHP' => '#4F5D95', 'Elixir' => '#6e4a7e', 'Java' => '#b07219', 'Kotlin' => '#A97BFF',
        'C#' => '#178600', 'C' => '#555555', 'C++' => '#f34b7d', 'HTML' => '#e34c26', 'CSS' => '#563d7c',
        'SCSS' => '#c6538c', 'Sass' => '#a53b70', 'Vue' => '#41b883', 'Svelte' => '#ff3e00', 'Shell' => '#89e051',
        'Bash' => '#89e051', 'Lua' => '#000080', 'Ruby' => '#701516', 'Dart' => '#00B4AB', 'Swift' => '#F05138',
        'Dockerfile' => '#384d54', 'Makefile' => '#427819', 'SQL' => '#e38c00', 'PLpgSQL' => '#336790',
        'YAML' => '#cb171e', 'JSON' => '#292929', 'TOML' => '#9c4221', 'Markdown' => '#083fa1', 'Zig' => '#ec915c',
        'Nix' => '#7e7eff', 'Haskell' => '#5e5086', 'Scala' => '#c22d40', 'F#' => '#b845fc', 'Solidity' => '#aa6746',
        'Assembly' => '#6e4c13', 'Erlang' => '#B83998', 'Clojure' => '#db5855', 'PowerShell' => '#012456',
        'Objective-C' => '#438eff', 'Perl' => '#0298c3', 'R' => '#198CE7', 'GraphQL' => '#e10098',
        'Handlebars' => '#f7931e', 'Twig' => '#c1d026', 'Blade' => '#f7523f', 'GDScript' => '#355570',
        'ShaderLab' => '#222c37', 'HLSL' => '#aace60', 'GLSL' => '#5686a5', 'Astro' => '#ff5a03', 'MDX' => '#fcb32c',
    ];

    public static function for(?string $language): string
    {
        return self::COLORS[(string) $language] ?? self::UNKNOWN;
    }
}
