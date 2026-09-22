<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Polyfill\Intl\Grapheme;

\define('SYMFONY_GRAPHEME_CLUSTER_RX', '\X');

/**
 * Partial intl implementation in pure PHP.
 *
 * Implemented:
 * - grapheme_extract  - Extract a sequence of grapheme clusters from a text buffer, which must be encoded in UTF-8
 * - grapheme_stripos  - Find position (in grapheme units) of first occurrence of a case-insensitive string
 * - grapheme_stristr  - Returns part of haystack string from the first occurrence of case-insensitive needle to the end of haystack
 * - grapheme_strlen   - Get string length in grapheme units
 * - grapheme_strpos   - Find position (in grapheme units) of first occurrence of a string
 * - grapheme_strripos - Find position (in grapheme units) of last occurrence of a case-insensitive string
 * - grapheme_strrpos  - Find position (in grapheme units) of last occurrence of a string
 * - grapheme_strstr   - Returns part of haystack string from the first occurrence of needle to the end of haystack
 * - grapheme_substr   - Return part of a string
 * - grapheme_str_split - Splits a string into an array of individual or chunks of graphemes
 * - grapheme_levenshtein - Calculate the grapheme-unit Levenshtein distance between two strings
 * - grapheme_strrev - Reverse a string by grapheme clusters
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class Grapheme
{
    // Table 3-7 of the Unicode standard for the well-formed byte sequences (group 1),
    // then the maximal subparts an ill-formed sequence is made of (group 2).
    private const UTF8_CHAR_OR_SUBPART_RX = '/([\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|[\xEE-\xEF][\x80-\xBF]{2}|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|(\xF0[\x90-\xBF][\x80-\xBF]?|[\xF1-\xF3][\x80-\xBF]{1,2}|\xF4[\x80-\x8F][\x80-\xBF]?|\xE0[\xA0-\xBF]|[\xE1-\xEC][\x80-\xBF]|\xED[\x80-\x9F]|[\xEE-\xEF][\x80-\xBF]|[\x80-\xFF])/s';

    private const CASE_FOLD = [
        ['µ', 'ſ', "\xCD\x85", 'ς', "\xCF\x90", "\xCF\x91", "\xCF\x95", "\xCF\x96", "\xCF\xB0", "\xCF\xB1", "\xCF\xB5", "\xE1\xBA\x9B", "\xE1\xBE\xBE"],
        ['μ', 's', 'ι',        'σ', 'β',        'θ',        'φ',        'π',        'κ',        'ρ',        'ε',        "\xE1\xB9\xA1", 'ι'],
    ];

    // indexed by the $mode argument of grapheme_position()
    private const POSITION_FUNCTIONS = ['grapheme_strpos', 'grapheme_stripos', 'grapheme_strrpos', 'grapheme_strripos'];

    public static function grapheme_extract($s, $size, $type = \GRAPHEME_EXTR_COUNT, $start = 0, &$next = 0)
    {
        if (0 > $start) {
            $start = \strlen($s) + $start;
        }

        if (!\is_scalar($s)) {
            $hasError = false;
            set_error_handler(static function () use (&$hasError) { $hasError = true; });
            $next = substr($s, $start);
            restore_error_handler();
            if ($hasError) {
                substr($s, $start);
                $s = '';
            } else {
                $s = $next;
            }
        } else {
            $s = substr($s, $start);
        }
        $size = (int) $size;
        $type = (int) $type;
        $start = (int) $start;

        if (\GRAPHEME_EXTR_COUNT !== $type && \GRAPHEME_EXTR_MAXBYTES !== $type && \GRAPHEME_EXTR_MAXCHARS !== $type) {
            if (80000 > \PHP_VERSION_ID) {
                return false;
            }

            throw new \ValueError('grapheme_extract(): Argument #3 ($type) must be one of GRAPHEME_EXTR_COUNT, GRAPHEME_EXTR_MAXBYTES, or GRAPHEME_EXTR_MAXCHARS');
        }

        if (!isset($s[0]) || 0 > $size || 0 > $start) {
            return false;
        }
        if (0 === $size) {
            return '';
        }

        $next = $start;

        $s = preg_split('/('.SYMFONY_GRAPHEME_CLUSTER_RX.')/u', "\r\n".$s, $size + 1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);

        if (!isset($s[1])) {
            return false;
        }

        $i = 1;
        $ret = '';

        do {
            if (\GRAPHEME_EXTR_COUNT === $type) {
                --$size;
            } elseif (\GRAPHEME_EXTR_MAXBYTES === $type) {
                $size -= \strlen($s[$i]);
            } else {
                $size -= iconv_strlen($s[$i], 'UTF-8//IGNORE');
            }

            if ($size >= 0) {
                $ret .= $s[$i];
            }
        } while (isset($s[++$i]) && $size > 0);

        $next += \strlen($ret);

        return $ret;
    }

    public static function grapheme_strlen($s)
    {
        preg_replace('/'.SYMFONY_GRAPHEME_CLUSTER_RX.'/u', '', $s, -1, $len);

        return 0 === $len && '' !== $s ? null : $len;
    }

    public static function grapheme_substr($s, $start, $len = null)
    {
        if (null === $len) {
            $len = 2147483647;
        }

        if (!self::isUtf8($s)) {
            return false;
        }

        preg_match_all('/'.SYMFONY_GRAPHEME_CLUSTER_RX.'/u', $s, $s);

        $slen = \count($s[0]);
        $start = (int) $start;

        if (0 > $start) {
            $start += $slen;
        }
        if (0 > $start) {
            if (\PHP_VERSION_ID < 80000) {
                return false;
            }

            $start = 0;
        }
        if ($start >= $slen) {
            return \PHP_VERSION_ID >= 80000 ? '' : false;
        }

        $rem = $slen - $start;

        if (0 > $len) {
            $len += $rem;
        }
        if (0 === $len) {
            return '';
        }
        if (0 > $len) {
            return \PHP_VERSION_ID >= 80000 ? '' : false;
        }
        if ($len > $rem) {
            $len = $rem;
        }

        return implode('', \array_slice($s[0], $start, $len));
    }

    public static function grapheme_strpos($s, $needle, $offset = 0)
    {
        return self::grapheme_position($s, $needle, $offset, 0);
    }

    public static function grapheme_stripos($s, $needle, $offset = 0)
    {
        return self::grapheme_position($s, $needle, $offset, 1);
    }

    public static function grapheme_strrpos($s, $needle, $offset = 0)
    {
        return self::grapheme_position($s, $needle, $offset, 2);
    }

    public static function grapheme_strripos($s, $needle, $offset = 0)
    {
        return self::grapheme_position($s, $needle, $offset, 3);
    }

    public static function grapheme_stristr($s, $needle, $beforeNeedle = false)
    {
        if (!self::isUtf8($s) || !self::isUtf8($needle)) {
            return false;
        }

        return mb_stristr($s, $needle, $beforeNeedle, 'UTF-8');
    }

    public static function grapheme_strstr($s, $needle, $beforeNeedle = false)
    {
        if (!self::isUtf8($s) || !self::isUtf8($needle)) {
            return false;
        }

        return mb_strstr($s, $needle, $beforeNeedle, 'UTF-8');
    }

    public static function grapheme_str_split($s, $len = 1)
    {
        if (1 > $len || 1073741823 < $len) {
            throw new \ValueError('grapheme_str_split(): Argument #2 ($length) must be greater than 0 and less than or equal to 1073741823');
        }

        if ('' === $s) {
            return [];
        }

        if (preg_match_all('/('.SYMFONY_GRAPHEME_CLUSTER_RX.')/u', $s, $matches)) {
            $graphemes = $matches[0];
        } else {
            $graphemes = self::splitIllFormed($s);
        }

        if (!$graphemes) {
            return false;
        }

        if (1 === $len) {
            return $graphemes;
        }

        $chunks = array_chunk($graphemes, $len);

        foreach ($chunks as &$chunk) {
            $chunk = implode('', $chunk);
        }

        return $chunks;
    }

    public static function grapheme_levenshtein($s1, $s2, $insertion_cost = 1, $replacement_cost = 1, $deletion_cost = 1)
    {
        if (!preg_match('//u', $s1) || !preg_match('//u', $s2)) {
            return false;
        }

        if (0 > $insertion_cost || 0 > $replacement_cost || 0 > $deletion_cost) {
            throw new \ValueError('grapheme_levenshtein(): Argument #3 ($insertion_cost), #4 ($replacement_cost), and #5 ($deletion_cost) must be greater than or equal to 0');
        }

        preg_match_all('/'.SYMFONY_GRAPHEME_CLUSTER_RX.'/u', $s1, $s1);
        preg_match_all('/'.SYMFONY_GRAPHEME_CLUSTER_RX.'/u', $s2, $s2);

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

    private static function grapheme_position($s, $needle, $offset, $mode)
    {
        $needle = (string) $needle;
        if (80000 > \PHP_VERSION_ID && !preg_match('/./us', $needle)) {
            return false;
        }
        $s = (string) $s;
        // let the empty string through: it accepts no offset but 0, which is checked below
        if ('' !== $s && !preg_match('/./us', $s)) {
            return false;
        }
        if ($offset && ($offset > ($len = self::grapheme_strlen($s)) || $offset < -$len)) {
            if (80000 > \PHP_VERSION_ID) {
                return false;
            }

            throw new \ValueError(self::POSITION_FUNCTIONS[$mode].'(): Argument #3 ($offset) must be contained in argument #1 ($haystack)');
        }
        if ('' === $s) {
            return false;
        }
        if ($offset > 0) {
            $s = self::grapheme_substr($s, $offset);
        } elseif ($offset < 0) {
            if (2 > $mode) {
                $offset += $len;
                $s = self::grapheme_substr($s, $offset);
                if (0 > $offset) {
                    $offset = 0;
                }
            } elseif (0 > $offset += self::grapheme_strlen($needle)) {
                $s = self::grapheme_substr($s, 0, $offset);
                $offset = 0;
            } else {
                $offset = 0;
            }
        }

        // As UTF-8 is self-synchronizing, and we have ensured the strings are valid UTF-8,
        // we can use normal binary string functions here. For case-insensitive searches,
        // case fold the strings first.
        $caseInsensitive = $mode & 1;
        $reverse = $mode & 2;
        if ($caseInsensitive) {
            // Use the same case folding mode as mbstring does for mb_stripos().
            // Stick to SIMPLE case folding to avoid changing the length of the string, which
            // might result in offsets being shifted.
            $mode = \defined('MB_CASE_FOLD_SIMPLE') ? \MB_CASE_FOLD_SIMPLE : \MB_CASE_LOWER;
            $s = mb_convert_case($s, $mode, 'UTF-8');
            $needle = mb_convert_case($needle, $mode, 'UTF-8');

            if (!\defined('MB_CASE_FOLD_SIMPLE')) {
                $s = str_replace(self::CASE_FOLD[0], self::CASE_FOLD[1], $s);
                $needle = str_replace(self::CASE_FOLD[0], self::CASE_FOLD[1], $needle);
            }
        }
        if ($reverse) {
            $needlePos = strrpos($s, $needle);
        } else {
            $needlePos = strpos($s, $needle);
        }

        return false !== $needlePos ? self::grapheme_strlen(substr($s, 0, $needlePos)) + $offset : false;
    }

    public static function grapheme_strrev(string $string)
    {
        if (!preg_match('//u', $string)) {
            return false;
        }

        $units = grapheme_str_split($string);

        if (false === $units) {
            return false;
        }

        return implode('', array_reverse($units));
    }

    private static function isUtf8(?string $s)
    {
        $s = $s ?? '';

        return '' === $s || preg_match('/./us', $s);
    }

    /**
     * Splits a string that is not valid UTF-8 into grapheme clusters.
     *
     * The intl extension keeps the ill-formed bytes and breaks around them the
     * way it would around the U+FFFD each maximal subpart stands for. Standing
     * one in for real makes the cluster regexp applicable, then each cluster is
     * mapped back to the bytes it was made of.
     */
    private static function splitIllFormed($s)
    {
        preg_match_all(self::UTF8_CHAR_OR_SUBPART_RX, $s, $matches, \PREG_SET_ORDER);

        $segments = [];
        $segmentAt = [];
        $sanitized = '';

        foreach ($matches as $m) {
            $segmentAt[\strlen($sanitized)] = \count($segments);

            if (isset($m[2]) && '' !== $m[2]) {
                $segments[] = $m[2];
                $sanitized .= "\xEF\xBF\xBD";
            } else {
                $segments[] = $m[1];
                $sanitized .= $m[1];
            }
        }

        $segmentAt[\strlen($sanitized)] = \count($segments);

        preg_match_all('/'.SYMFONY_GRAPHEME_CLUSTER_RX.'/u', $sanitized, $clusters, \PREG_OFFSET_CAPTURE);

        $graphemes = [];

        foreach ($clusters[0] as $cluster) {
            $start = $segmentAt[$cluster[1]];
            $graphemes[] = implode('', \array_slice($segments, $start, $segmentAt[$cluster[1] + \strlen($cluster[0])] - $start));
        }

        return $graphemes;
    }
}
