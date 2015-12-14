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

/**
 * @var string The first line of output
 */
$startswith = "    charset_table = ";
/**
 * @var int Limit num. chars per output line
 */
$linewidth = 120;
/**
 * @var int Spaces to indent a continuation line
 */
$indent = 8;
/**
 * Minimun number of hex chars in each codepoint in output.
 */
$codePointDigits = 2;
/*
 * END OF USER CONFIGURATIONS
 * ============================================================================
 */

ini_set('default_charset', 'UTF-8');
ini_set('mbstring.func_overload', 0);

$excludeRanges = require(__DIR__ . '/range_config.php');

/**
 * Convert hex string to Sphinx Unicode code point literal.
 *
 * Given a hex string argument, return a unicode code point litieral in the
 * format used in a sphinx config file's charset_table part.
 *
 * @param string $codePoint Hex number
 *
 * @return string Sphinx format Unicode character litieral
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
/** @var array Terminal characters Sphinx will actually index*/
$terminals = [];
/** @var array $includes */
$includes = [];
/** string $output formatted charset_table elements */
$output = [];

// From the $ranges configuration, figure the ranges to include without collation.
$excludeRanges = array_merge($excludeRanges['collate'], $excludeRanges['exclude']);
foreach ($excludeRanges as $i => $range) {
    $excludeRanges[$i][0] = is_integer($range[0]) ? $range[0] : hexdec($range[0]);
    $excludeRanges[$i][1] = is_integer(end($range)) ? end($range) : hexdec(end($range));
    sort($excludeRanges[$i]);
}
sort($excludeRanges);

$includeFrom = 0;
foreach ($excludeRanges as $range) {
    if ($range[0] > $includeFrom) {
        $includes[] = $includeFrom === $range[0] - 1 ? [$includeFrom] : [$includeFrom, $range[0] - 1];
    }
    if ($range[1] >= $includeFrom) {
        $includeFrom = $range[1] + 1;
    }
}

$includeTo = hexdec('10FFFF');
$next = end($excludeRanges);
$next = end($next) + 1;
if ($next <= $includeTo) {
    $includes[] = $next === $includeTo ? [$includeTo] : [$includeFrom, $includeTo];
}

// parse each input line
foreach (file('php://stdin') as $line) {
    // search each input line for a tab character
    if (preg_match('/\t(.+)\s?$/u', $line, $m)) {
        // if the part after the tab ...
        if (preg_match('/^[0-9a-f]{1,5}$/', $m[1])) {
            // ... a single hex codepoint then it's a singleton,
            // add it to the list of singles
            $includes[] = [hexdec($m[1])];
        } elseif (preg_match('/^[0-9a-f]{1,5}(,[0-9a-f]{1,5})+$/', $m[1])) {
            // ... a comma separatred list of codepoints,
            // it's a set of chars to be folded to the frst of them,
            // split it and add to the list of sets
            $set = array_map('hexdec', explode(',', $m[1]));
            $includes[] = [$set[0]];
            $sets[] = $set;
        }
    }
}

sort($includes);
$combined = [array_shift($includes)];
$i = 0;
foreach ($includes as $include) {
    if ($include[0] <= end($combined[$i]) + 1) {
        if (end($include) > end($combined[$i])) {
            $combined[$i][1] = end($include);
        }
    } else {
        $combined[] = $include;
        $i += 1;
    }
}

// encode the included, not-collated ranges
foreach ($combined as $range) {
    $output[] = !isset($range[1]) ? u($range[0]) : u($range[0]) . '..' . u($range[1]);
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
echo $startswith;
$w = strlen($startswith);
$last = array_pop($output);
foreach ($output as $s) {
    $s .= ', ';
    if ($w + strlen($s) > $linewidth) {
        echo $leadstr;
        $w = $indent;
    }
    echo $s;
    $w += strlen($s);
}
print("$last\n");
