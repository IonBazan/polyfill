<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Polyfill\Php80;

/**
 * @author Ion Bazan <ion.bazan@gmail.com>
 *
 * @internal
 */
final class Php80
{
    public static function fdiv($dividend, $divisor)
    {
        $dividend = self::floatArg($dividend, __FUNCTION__, 1);
        $divisor = self::floatArg($divisor, __FUNCTION__, 2);

        if (\PHP_VERSION_ID >= 70000) {
            return (float) @($dividend / $divisor);
        }

        if (is_nan($dividend) || is_nan($divisor)) {
            return NAN;
        }

        if (($divisor === INF || $divisor === -INF) && $dividend !== 0.0) {
            return NAN;
        }

        if (0.0 === $divisor) {
            if ($dividend === 0.0) {
                return NAN;
            }

            return ($dividend < 0 xor $divisor < 0 xor self::isNegativeZero($dividend) xor self::isNegativeZero($divisor)) ? -INF : INF;
        }

        return (float) $dividend / $divisor;
    }

    private static function isNegativeZero($value)
    {
        return ((string) $value) === '-0'; // @todo make it work on PHP5.x
    }

    private static function floatArg($value, $caller, $pos)
    {
        if (\is_float($value)) {
            return $value;
        }

        if (!\is_numeric($value)) {
            throw new \TypeError(sprintf('%s() expects parameter %d to be float, %s given', $caller, $pos, \gettype($value)));
        }

        return (float) $value;
    }
}
