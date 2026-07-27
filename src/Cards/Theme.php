<?php

declare(strict_types=1);

namespace Ghapi\Cards;

final class Theme
{
    public function __construct(
        public readonly string $name,
        public readonly string $background,
        public readonly string $text,
        public readonly string $title,
        public readonly string $icon,
        public readonly string $muted,
        public readonly string $border,
        public readonly array $heatLevels,
    ) {
    }

    public static function resolve(?string $name): self
    {
        return match (strtolower((string) $name)) {
            'light' => new self('light', '#ffffff', '#24292f', '#0969da', '#8250df', '#57606a', '#d0d7de', ['#ebedf0', '#9be9a8', '#40c463', '#30a14e', '#216e39', '#0a4423']),
            default => new self('tokyonight', '#1a1b27', '#a9b1d6', '#7aa2f7', '#bb9af7', '#565f89', '#2f3549', ['#232433', '#0e4429', '#006d32', '#26a641', '#39d353', '#57ff70']),
        };
    }
}
