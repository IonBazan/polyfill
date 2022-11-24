<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Polyfill\Tests\Php82;

use PHPUnit\Framework\TestCase;
use Symfony\Polyfill\Php82\Php82;

class Php82Test extends TestCase
{
    /**
     * @covers \Symfony\Polyfill\Php82\Php82::ini_parse_quantity
     * @dataProvider iniValues
     */
    public function testIniParseQuantity(string $iniValue, int $expectedValue, ?string $warning = null)
    {
        $this->assertSame($expectedValue, @ini_parse_quantity($iniValue));

        if (null !== $warning) {
            $this->expectWarning();
            $this->expectWarningMessageMatches($warning);
        }

        ini_parse_quantity($iniValue);
    }

    public static function iniValues(): iterable
    {
        return [
            ['1K', 1024],
            ['1k', 1024],
            ['5M', 1024 * 1024 * 5],
            ['5 M', 1024 * 1024 * 5],
            ['-5M', 1024 * 1024 * -5],
            ['-696969', -696969],
            ['696969', 696969],
            ['1KMG', 1024 * 1024 * 1024, '/Invalid quantity "1KMG", interpreting as "1G" for backwards compatibility/'],
            [' 20.9 KM', 1024 * 1024 * 20, '/Invalid quantity " 20.9 KM", interpreting as "20M" for backwards compatibility/'],
            ['21.37X', 21, '/unknown multiplier/'],
            ['20.1 KM', 1024 * 1024 * 20, '/Invalid quantity "20.1 KM", interpreting as "20M" for backwards compatibility/'],
            ['0x20', 0x20],
            ['0xFe', 0xFE],
            ['0b1111', 0b1111],
            [' 0o20', 020],
            ['0o20K', 1024 * 020],
            ['0', 0],
            ['1', 1],
            ['', 0],
        ];
    }
}
