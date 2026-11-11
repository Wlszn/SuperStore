<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Draws text as a Code 128 (set B) barcode in SVG, for the membership card.
 * Any USB barcode scanner reads it and types the membership number (SS100001)
 * into the checkout kiosk.
 */
final class Barcode
{
    /** Bar/space widths of the 107 Code 128 patterns (index = symbol value). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];
    private const START_B = 104;
    private const STOP = 106;

    public static function code128Svg(string $text, int $height = 70, float $module = 2.0): string
    {
        // Values: start, one per character (ASCII 32-126), checksum, stop.
        $values = [self::START_B];
        $sum = self::START_B;
        foreach (str_split($text) as $i => $char) {
            $value = ord($char) - 32;
            if ($value < 0 || $value > 94) {
                $value = 0; // not printable: drawn as a space
            }
            $values[] = $value;
            $sum += $value * ($i + 1);
        }
        $values[] = $sum % 103;
        $values[] = self::STOP;

        $x = 10 * $module; // quiet zone
        $bars = '';
        foreach ($values as $value) {
            foreach (str_split(self::PATTERNS[$value]) as $j => $width) {
                $w = (int) $width * $module;
                if ($j % 2 === 0) { // even positions are bars, odd are spaces
                    $bars .= sprintf('<rect x="%.1f" y="0" width="%.1f" height="%d"/>', $x, $w, $height);
                }
                $x += $w;
            }
        }
        $width = $x + 10 * $module;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.1f %d" class="barcode" role="img" aria-label="%s">'
            . '<rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g></svg>',
            $width,
            $height,
            htmlspecialchars($text, ENT_QUOTES),
            $bars
        );
    }
}
