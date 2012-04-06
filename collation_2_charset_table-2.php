<?php
/*
 * USER CONFIGURATIONS
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
$padto = 3;
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
 * Convert hex string to Sphinx Unicode literal
 *
 * Given a hex string argument, return a unicode character litieral in the
 * format used in a sphinx config file's charset_table part.
 *
 * @param $s Hex number as string
 * @return string Sphinx format Unicode character litieral
 */
function u($s) {
	global $padto;
	return preg_match('/[1-9a-fA-F][0-9a-fA-F]*$/', $s, $m)
		? 'U+' . str_pad($m[0], $padto, "0", STR_PAD_LEFT)
		: $s;
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
 * @var string Terminal characters Sphinx will actually index
 */
$singles = array();

// parse each input line
foreach ($lines as $line) {

	// search each input line for a tab character
	if (preg_match('/^(.+)\t(.+)$/u', $line, $m)) {

		// if the part after the tab on the unput line is ...
		if (preg_match('/^[0-9a-f]{1,5}$/', $m[2])
		) {
		// ... a single hex codepoint then it's a singleton,
		// add it to the list of singles
			$singles[] = '0x' . $m[2];

		} elseif (preg_match('/^[0-9a-f]{1,5}(,[0-9a-f]{1,5})+$/', $m[2])
		) {
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
	for ($i = 1; $i < count($codes); ++$i)
		$t[] = u($codes[$i]) . "->$to";
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
