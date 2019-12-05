<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Polyfill\Php80 as p;

if (PHP_VERSION_ID < 80000) {
    if (!function_exists('fdiv')) {
        function fdiv($dividend, $divisor) { return p\Php80::fdiv($dividend, $divisor); }
    }
}
