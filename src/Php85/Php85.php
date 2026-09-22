<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Polyfill\Php85;

/**
 * @author Pierre Ambroise <pierre27.ambroise@gmail.com>
 * @author Alexander Schranz <alexander@sulu.io>
 *
 * @internal
 */
final class Php85
{
    public static function get_error_handler(): ?callable
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler;
    }

    public static function get_exception_handler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    public static function array_first(array $array)
    {
        foreach ($array as $value) {
            return $value;
        }

        return null;
    }

    public static function array_last(array $array)
    {
        return $array ? current(\array_slice($array, -1)) : null;
    }

    private const RTL_SCRIPTS = [
        'Adlm' => true, 'Arab' => true, 'Armi' => true, 'Hebr' => true,
        'Mand' => true, 'Mani' => true, 'Mend' => true, 'Nkoo' => true,
        'Orkh' => true, 'Phnx' => true, 'Rohg' => true, 'Samr' => true,
        'Syrc' => true, 'Thaa' => true, 'Yezi' => true,
    ];

    private const LANG_TO_SCRIPT = [
        'ar' => 'Arab',
        'ckb' => 'Arab',
        'dv' => 'Thaa',
        'fa' => 'Arab',
        'he' => 'Hebr',
        'ku' => 'Arab',
        'nqo' => 'Nkoo',
        'ps' => 'Arab',
        'sd' => 'Arab',
        'ug' => 'Arab',
        'ur' => 'Arab',
        'yi' => 'Hebr',
    ];

    public static function locale_is_right_to_left(string $locale): bool
    {
        if ('' === $locale) {
            return false;
        }

        $parts = preg_split('/[_-]/', $locale);
        $language = strtolower($parts[0]);

        foreach ($parts as $part) {
            if (4 === \strlen($part) && ctype_alpha($part)) {
                return isset(self::RTL_SCRIPTS[ucfirst(strtolower($part))]);
            }
        }

        return isset(self::LANG_TO_SCRIPT[$language]) && isset(self::RTL_SCRIPTS[self::LANG_TO_SCRIPT[$language]]);
    }

    public static function grapheme_levenshtein(string $s1, string $s2, int $insertion_cost = 1, int $replacement_cost = 1, int $deletion_cost = 1)
    {
        if (!preg_match('//u', $s1) || !preg_match('//u', $s2)) {
            return false;
        }

        if (0 > $insertion_cost || 0 > $replacement_cost || 0 > $deletion_cost) {
            throw new \ValueError('grapheme_levenshtein(): Argument #3 ($insertion_cost), #4 ($replacement_cost), and #5 ($deletion_cost) must be greater than or equal to 0');
        }

        $regex = '\X';

        preg_match_all('/'.$regex.'/u', $s1, $s1);
        preg_match_all('/'.$regex.'/u', $s2, $s2);

        $s1 = $s1[0];
        $s2 = $s2[0];
        $l1 = \count($s1);
        $l2 = \count($s2);

        // Keep the rows as short as possible. Reversing the transformation
        // swaps the meaning of insertion and deletion.
        if ($l1 < $l2) {
            [$s1, $s2] = [$s2, $s1];
            [$l1, $l2] = [$l2, $l1];
            [$insertion_cost, $deletion_cost] = [$deletion_cost, $insertion_cost];
        }

        if (0 === $l2) {
            return $l1 * $deletion_cost;
        }

        $previousRow = $currentRow = array_fill(0, $l2 + 1, 0);
        for ($j = 1; $j <= $l2; ++$j) {
            $previousRow[$j] = $previousRow[$j - 1] + $insertion_cost;
        }

        for ($i = 1; $i <= $l1; ++$i) {
            $currentRow[0] = $previousRow[0] + $deletion_cost;

            for ($j = 1; $j <= $l2; ++$j) {
                $cost = ($s1[$i - 1] === $s2[$j - 1]) ? 0 : $replacement_cost;
                $currentRow[$j] = min($previousRow[$j] + $deletion_cost, $currentRow[$j - 1] + $insertion_cost, $previousRow[$j - 1] + $cost);
            }

            [$previousRow, $currentRow] = [$currentRow, $previousRow];
        }

        return $previousRow[$l2];
    }
}
