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
$startswith = "\tcharset_table = ";
/**
 * @var int Limit num. chars per output line
 */
$linewidth = 100;
/**
 * @var int How wide are your tabs?
 */
$tabwidth = 8;
/**
 * @var int Num. tabs to indent a continuation line
 */
$tabsindent = 2;
/**
 * @var int Min. num. hex digits per codepoint literal
 */
$padto = 4;
/*
 * END OF USER CONFIGURATIONS
 * ============================================================================
 */

ini_set('default_charset', 'UTF-8');
ini_set('mbstring.func_overload', 0);

// Figure  from config useful values for data output
$leadstr = str_repeat("\t", $tabsindent);
$leadwidth = $tabsindent * ($tabwidth);

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
    global $padto;

    return preg_match('/[1-9a-fA-F][0-9a-fA-F]*$/', $codePoint, $m)
        ? 'U+' . str_pad($m[0], $padto, "0", STR_PAD_LEFT)
        : $codePoint;
}

// Read lines from stdin until eof
while (!feof(STDIN)) {
    $lines[] = fgets(STDIN);
}
if (!$lines) {
    exit();
}

/**
 * @var array[] Sets of characters collated with equal value, to be folded by Sphinx
 */
$sets = array();
/**
 * @var string[] Terminal characters Sphinx will actually index
 */
$singles = array();

// parse each input line
foreach ($lines as $line) {
    // search each input line for a tab character
    if (preg_match('/^(.+)\t(.+)$/u', $line, $m)) {
        // if the part after the tab on the unput line is ...
        if (preg_match('/^[0-9a-f]{1,5}$/', $m[2])) {
            // ... a single hex codepoint then it's a singleton,
            // add it to the list of singles
            $singles[] = '0x' . $m[2];
        } elseif (preg_match('/^[0-9a-f]{1,5}(,[0-9a-f]{1,5})+$/', $m[2])) {
            // ... a comma separatred list of codepoints,
            // it's a set of chars to be folded to the frst of them,
            // split it and add to the list of sets
            $sets[] = preg_split('/,/', $m[2]);
        }
    }
}

// add the folding targets to singles
foreach ($sets as $codes) {
    $singles[] = '0x' . $codes[0];
}

// encode the rules for sphinx.
// do the singles (folding targets) first
sort($singles);

// run detection state machine var
$run = false;

// collect folding rules in $t
$t = array();

// $s is an output string
$s = u($singles[0]);
for ($i = 1; $i < count($singles) - 1; $i++) {
    // detect runs of consecutive codeponts and use
    // Sphinx's .. notation, e.g.: 'U+041..U+05A'
    if ($run) {
        if ($singles[$i] != $singles[$i + 1] - 1) {
            $s .= ".." . u($singles[$i]);
            $run = false;
        }
    } else {
        $run = $singles[$i] == $singles[$i - 1] + 1
            && $singles[$i] == $singles[$i + 1] - 1;
        if (!$run) {
            $t[] = $s;
            $s = u($singles[$i]);
        }
    }
}

// finish up after the end of the above loop
if ($run) {
    $s .= ".." . u($singles[$i]);
    $t[] = $s;
} else {
    $t[] = u($singles[$i]);
}

// encode the folding rules
foreach ($sets as $codes) {
    $to = u($codes[0]);
    for ($i = 1; $i < count($codes); ++$i) {
        $t[] = u($codes[$i]) . "->$to";
    }
}

// is there anything to output?
if (!$t) {
    exit(0);
}

// format output for the sphinx config file
print($startswith);
$w = strlen($startswith);
$last = array_pop($t);
foreach ($t as $s) {
    $s .= ', ';
    if ($w + strlen($s) > $linewidth) {
        print("\\\n$leadstr");
        $w = $leadwidth;
    }
    print($s);
    $w += strlen($s);
}
print("$last\n");
