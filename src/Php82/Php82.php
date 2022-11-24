<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Polyfill\Php82;

final class Php82
{
    // https://php.watch/versions/8.2/ini_parse_quantity#polyfill
    public static function ini_parse_quantity(string $shorthand): int
    {
        $original_shorthand = $shorthand;
        $multiplier = 1;
        $sign = '';
        $return_value = 0;

        $shorthand = trim($shorthand);

        // Return 0 for empty strings.
        if ($shorthand === '') {
            return 0;
        }

        // Accept + and - as the sign.
        if ($shorthand[0] === '-' || $shorthand[0] === '+') {
            if ($shorthand[0] === '-') {
                $multiplier = -1;
                $sign = '-';
            }
            $shorthand = substr($shorthand, 1);
        }

        $base = 10;
        $digitRegex = '0-9';

        if ($shorthand[0] === '0') {
            switch (strtoupper($shorthand[1] ?? '')) {
                case 'X':
                    $base = 16;
                    $digitRegex = '0-9A-Fa-f';
                    $shorthand = substr($shorthand, 2);
                    break;
                case 'B':
                    $base = 2;
                    $digitRegex = '0-1';
                    $shorthand = substr($shorthand, 2);
                    break;
                case 'O':
                    $base = 8;
                    $digitRegex = '0-7';
                    $shorthand = substr($shorthand, 2);
                    break;
            }
        }

        // If there is no suffix, return the integer value with the sign.
        if (preg_match('/^['.$digitRegex.']+$/', $shorthand, $matches)) {
            return self::parseInt($matches[0], $base) * $multiplier;
        }

        // Return 0 with a warning if there are no leading digits
        if (preg_match('/^['.$digitRegex.']/', $shorthand) === 0) {
            trigger_error(
                sprintf(
                    'Invalid quantity "%s": no valid leading digits, interpreting as "0" for backwards compatibility',
                    $original_shorthand
                ),
                E_USER_WARNING
            );

            return $return_value;
        }

        // Removing whitespace characters.
        $shorthand = preg_replace('/\s/', '', $shorthand);

        $suffix = strtoupper(substr($shorthand, -1));
        switch ($suffix) {
            case 'K':
                $multiplier *= 1024;
                break;
            case 'M':
                $multiplier *= 1024 * 1024;
                break;
            case 'G':
                $multiplier *= 1024 * 1024 * 1024;
                break;
            default:
                preg_match('/['.$digitRegex.']+/', $shorthand, $matches);
                trigger_error(
                    sprintf(
                        'Invalid quantity "%s": unknown multiplier "%s", interpreting as "%d" for backwards compatibility',
                        $original_shorthand,
                        $suffix,
                        $sign.$matches[0]
                    ),
                    E_USER_WARNING
                );

                return self::parseInt($matches[0], $base) * $multiplier;
        }

        $stripped_shorthand = preg_replace('/^(['.$digitRegex.']+)([^'.$digitRegex.'].*)([kKmMgG])$/', '$1$3', $shorthand, -1, $count);
        if ($count > 0) {
            trigger_error(
                sprintf(
                    'Invalid quantity "%s", interpreting as "%s" for backwards compatibility',
                    $original_shorthand,
                    $sign.$stripped_shorthand
                ),
                E_USER_WARNING
            );
        }

        preg_match('/['.$digitRegex.']+/', $shorthand, $matches);

        $multiplied = $matches[0] * $multiplier;
        if (is_float($multiplied)) {
            trigger_error(
                sprintf(
                    'Invalid quantity "%s": value is out of range, using overflow result for backwards compatibility',
                    $original_shorthand
                ),
                E_USER_WARNING
            );
        }

        return (int)(self::parseInt($matches[0], $base) * $multiplier);
    }

    private static function parseInt(string $value, int $base): int
    {
        switch ($base) {
            case 2: return bindec($value);
            case 8: return octdec($value);
            case 16: return hexdec($value);
            default: return (int)$value;
        }
    }
}
