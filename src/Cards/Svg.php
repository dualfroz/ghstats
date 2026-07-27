<?php

declare(strict_types=1);

namespace Ghapi\Cards;

final class Svg
{
    public const FONT = "'Segoe UI', Ubuntu, 'Helvetica Neue', Helvetica, Arial, sans-serif";

    public static function document(int $width, int $height, Theme $theme, bool $hideBorder, string $ariaLabel, string $body): string
    {
        $style = '<style>'
            . 'text{font-family:' . self::FONT . ';}'
            . '.fadein{opacity:0;animation:ghsFade 0.7s ease forwards;}'
            . '@keyframes ghsFade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}'
            . '.grow-up{transform-box:fill-box;transform-origin:center bottom;animation:ghsGrowUp 0.75s cubic-bezier(0.22,1,0.36,1) forwards;}'
            . '@keyframes ghsGrowUp{from{transform:scaleY(0);}to{transform:scaleY(1);}}'
            . '.grow-right{transform-box:fill-box;transform-origin:left center;animation:ghsGrowRight 0.75s cubic-bezier(0.22,1,0.36,1) forwards;}'
            . '@keyframes ghsGrowRight{from{transform:scaleX(0);}to{transform:scaleX(1);}}'
            . '.pie-grow{transform-box:fill-box;transform-origin:center;opacity:0;animation:ghsPie 0.7s ease forwards;}'
            . '@keyframes ghsPie{from{transform:scale(0.6);opacity:0;}to{transform:scale(1);opacity:1;}}'
            . '</style>';
        $border = $hideBorder ? '' : ' stroke="' . $theme->border . '" stroke-width="1"';
        return '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height
            . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . self::escape($ariaLabel) . '">'
            . $style
            . '<rect x="0.5" y="0.5" rx="6" width="' . ($width - 1) . '" height="' . ($height - 1) . '" fill="' . $theme->background . '"' . $border . '/>'
            . $body
            . '</svg>';
    }

    public static function title(int|float $x, int|float $y, Theme $theme, string $text): string
    {
        return '<text x="' . self::n($x) . '" y="' . self::n($y) . '" fill="' . $theme->title . '" font-size="18" font-weight="600">' . self::escape($text) . '</text>';
    }

    public static function text(int|float $x, int|float $y, string $fill, int $size, int $weight, string $content, ?string $anchor = null): string
    {
        return '<text x="' . self::n($x) . '" y="' . self::n($y) . '" fill="' . $fill . '" font-size="' . $size . '" font-weight="' . $weight . '"'
            . ($anchor === null ? '' : ' text-anchor="' . $anchor . '"') . '>' . self::escape($content) . '</text>';
    }

    public static function divider(int $width, int $y, Theme $theme): string
    {
        return '<line x1="25" y1="' . $y . '" x2="' . ($width - 25) . '" y2="' . $y . '" stroke="' . $theme->border . '" stroke-width="1"/>';
    }

    public static function fade(float $delay, string $content): string
    {
        return '<g class="fadein" style="animation-delay:' . self::n($delay) . 's">' . $content . '</g>';
    }

    public static function n(int|float $value): string
    {
        if ((float) $value === round((float) $value)) {
            return (string) (int) round((float) $value);
        }
        return sprintf('%.2f', $value);
    }

    public static function escape(string $raw): string
    {
        return htmlspecialchars($raw, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function length(string $text): int
    {
        return (int) preg_match_all('/./us', $text);
    }

    public static function truncate(string $text, int $max, string $ellipsis = '…'): string
    {
        if (preg_match_all('/./us', $text, $chars) && count($chars[0]) > $max) {
            return implode('', array_slice($chars[0], 0, $max - 1)) . $ellipsis;
        }
        return $text;
    }
}
