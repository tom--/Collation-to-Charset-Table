<?php

/*
 * Copyright (c) 2012, Tom Worster <fsb@thefsb.org>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with
 * or without fee is hereby granted, provided that the above copyright notice and this
 * permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD
 * TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN
 * NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL
 * DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER
 * IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN
 * CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

ini_set('default_charset', 'UTF-8');
ini_set('mbstring.func_overload', 0);

/*
 * Formatting configurations...
 */
/**
 * @var string The first line of output
 */
$startswith = "    charset_table = ";
/**
 * @var integer Max output line length
 */
$linewidth = 120;
/**
 * @var integer Continuation line indentation
 */
$indent = 8;
/**
 * @var integer Minimun number of hex chars in each codepoint in output.
 */
$codePointDigits = 2;
/**
 * @var bool Set true for a less readable charset_table.
 */
$stingyWhitespace = false;


/**
 * Generate a Unicode code point literal from hex or integer.
 *
 * @param integer|string $codePoint
 * @return string Code point in unicode notation, e.g. U+261C
 */
function u($codePoint)
{
    global $codePointDigits;

    if (is_integer($codePoint)) {
        return sprintf('U+%0' . $codePointDigits . 'x', $codePoint);
    }

    if (preg_match('{[0-9a-f]{1,6}$}i', $codePoint, $match)) {
        return sprintf('U+%0' . $codePointDigits . 'x', hexdec($match[0]));
    }

    return $codePoint;
}

/** @var array[] Sets of characters collated with equal value, to be folded by Sphinx */
$sets = [];

/** @var array[] Code points and ranges to include in indexes */
$index = [];

/** @var string[] formatted charset_table elements */
$output = [];

$config = require(__DIR__ . '/config.php');

// From the configuration, figure the ranges to include without collation, i.e. all of
// Unicode minus 'collate' ranges and 'exclude' ranges.
$excludeRanges = array_merge($config['collate'], $config['exclude']);
foreach ($excludeRanges as $i => $range) {
    $excludeRanges[$i][0] = is_integer($range[0]) ? $range[0] : hexdec($range[0]);
    $excludeRanges[$i][1] = is_integer(end($range)) ? end($range) : hexdec(end($range));
    sort($excludeRanges[$i]);
}
sort($excludeRanges);

$includeFrom = 0;
foreach ($excludeRanges as $range) {
    if ($range[0] > $includeFrom) {
        $index[] = $includeFrom === $range[0] - 1 ? [$includeFrom] : [$includeFrom, $range[0] - 1];
    }
    if ($range[1] >= $includeFrom) {
        $includeFrom = $range[1] + 1;
    }
}

$includeTo = hexdec('10FFFF');
$next = end($excludeRanges);
$next = end($next) + 1;
if ($next <= $includeTo) {
    $index[] = $next === $includeTo ? [$includeTo] : [$includeFrom, $includeTo];
}

// Parse each line of stdin.
foreach (file('php://stdin') as $line) {
    // Take the part of the line after the tab.
    if (preg_match('/\t(.+)\s?$/u', $line, $match)) {
        if (preg_match('/^[0-9a-f]{1,5}$/', $match[1])) {
            // The line is a singleton. Index it.
            $index[] = [hexdec($match[1])];
        } elseif (preg_match('/^[0-9a-f]{1,5}(,[0-9a-f]{1,5})+$/', $match[1])) {
            // The line is a folding set. Index its first codepoint and save the folding set.
            $set = array_map('hexdec', explode(',', $match[1]));
            $index[] = [$set[0]];
            $sets[] = $set;
        }
    }
}

// merge singletons and folding targets with the uncollated, indexed ranges into $combined
sort($index);
$combinedIndex = [array_shift($index)];
$i = 0;
foreach ($index as $range) {
    if ($range[0] <= end($combinedIndex[$i]) + 1) {
        if (end($range) > end($combinedIndex[$i])) {
            $combinedIndex[$i][1] = end($range);
        }
    } else {
        $combinedIndex[] = $range;
        $i += 1;
    }
}
unset($index);

// encode the combined code points and ranges to index
foreach ($combinedIndex as $range) {
    $output[] = $range[0] === end($range) ? u($range[0]) : u($range[0]) . '..' . u($range[1]);
}

// encode the folding rules
foreach ($sets as $set) {
    $to = '->' . u(array_shift($set));
    foreach ($set as $code) {
        $output[] = u($code) . $to;
    }
}

// format output for the sphinx config file
// Figure from config useful values for data output
$leadstr = "\\\n" . str_repeat(" ", $indent);
$separator = $stingyWhitespace ? ',' : ', ';
echo $startswith;
$w = strlen($startswith);
$last = array_pop($output);
foreach ($output as $s) {
    $s .= $separator;
    if ($w + strlen($s) > $linewidth) {
        echo $leadstr;
        $w = $indent;
    }
    echo $s;
    $w += strlen($s);
}
echo "$last\n";
