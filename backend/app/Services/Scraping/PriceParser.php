<?php

namespace App\Services\Scraping;

/**
 * Turns a human-readable price string ("£51.77", "1.299,00 EGP", "$1,024.99")
 * into a float. Returns null when no number can be found.
 */
final class PriceParser
{
    public static function parse(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        // Keep digits and separators only.
        $cleaned = preg_replace('/[^\d.,]/', '', $raw) ?? '';
        if ($cleaned === '') {
            return null;
        }

        $lastDot = strrpos($cleaned, '.');
        $lastComma = strrpos($cleaned, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Both present: the right-most separator is the decimal point.
            $decimalSep = $lastDot > $lastComma ? '.' : ',';
            $thousandsSep = $decimalSep === '.' ? ',' : '.';
            $cleaned = str_replace($thousandsSep, '', $cleaned);
            $cleaned = str_replace($decimalSep, '.', $cleaned);
        } elseif ($lastComma !== false) {
            // Comma only: decimal separator if it looks like "12,99", else thousands.
            $cleaned = self::looksLikeDecimal($cleaned, ',')
                ? str_replace(',', '.', $cleaned)
                : str_replace(',', '', $cleaned);
        } elseif ($lastDot !== false && ! self::looksLikeDecimal($cleaned, '.')) {
            // Dot used as a thousands separator, e.g. "1.299".
            $cleaned = str_replace('.', '', $cleaned);
        }

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private static function looksLikeDecimal(string $value, string $sep): bool
    {
        $pos = strrpos($value, $sep);

        return $pos !== false && strlen($value) - $pos - 1 <= 2;
    }
}
