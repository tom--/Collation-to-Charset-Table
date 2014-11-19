<?php
/*
 * Copyright (c) 2012, Tom Worster <fsb@thefsb.org>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/**
 * Generate Sphinx charset folding rules for these Unicode character ranges.
 *
 * You may want to manually add a some terminal ranges to Sphinx's charset_table. For
 * example, with the $ranges in this example, I want to cover all "reasonable" alphabets
 * in the range 0000-FFFF. But utf8_general_ci doesn't do anything to most of that range.
 * So it's easier to use this script on a portion of the range and enter the rest as
 * terminals in charset_table manually, e.g.:
 *
 *   U+590..109F, U+1100..167F, U+1700..U+1DFF, U+2C60..U+2DFF, \
 *   U+2E80..U+2FFF, U+3040..U+DFFF, U+F900..U+FBFF, U+FE70..U+FFEF, \
 *
 * @var string[] Array of hex ranges as strings
 */
$ranges = array(
	'0000-02AF',
	'0370-058F',
	'10A0-10FF',
	'1E00-1FFF',
);
/**
 * @var string Name of the working database to use for processing.
 */
$dbname = 'my_collation_db';
/**
 * @var string Name of the working table to use for processing.
 */
$tablename = 'my_table';
/**
 * @var string Collation to use (utf8 only)
 */
$collation = 'utf8_general_ci';
/**
 * @var string PDO DSN
 */
$pdo_dsn = 'mysql:unix_socket=/tmp/mysql.sock';
/**
 * @var string MySQL user name
 */
$pdo_user = 'root';
/**
 * @var string MySQL password
 */
$pdo_pass = prompt_silent('MySQL password for use: ');
/*
 * END OF USER CONFIGURATIONS
 * ============================================================================
 */


ini_set('default_charset', 'UTF-8');

$db = new PDO($pdo_dsn, $pdo_user, $pdo_pass);
$db->query("SET NAMES 'utf8' COLLATE '$collation';");
$db->query("DROP DATABASE IF EXISTS `$dbname`;");
$db->query("CREATE DATABASE `$dbname` DEFAULT CHARACTER SET utf8 COLLATE $collation;");
$db->query("USE $dbname;");
$db->query(
	"CREATE TABLE IF NOT EXISTS `$tablename` (
		  `dec` int(11) NOT NULL,
		  `mychar` char(1) NOT NULL,
		  `hex` char(4) NOT NULL,
		  PRIMARY KEY  (`dec`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;"
);

// The SQL insert clauses without data
$ins = "INSERT IGNORE INTO `$tablename` (`dec`, `mychar`, `hex`) VALUES ";

// Get the max MySQL packet size
$max_data_per_packet = $db->query("SELECT @@session.max_allowed_packet;")->fetch();

// Figure the max number of data bytes per MySQL packet
$max_data_per_packet = $max_data_per_packet[0] - 2 * mb_strlen($ins, 'ISO-8859-1');
$rows_per_insert = $max_data_per_packet / 70;

// Add a row to the working table for every Unicode char in the ranges specified
$s = array();
foreach ($ranges as $range) {
	if (preg_match('/^([0-9A-F]{1,6})-([0-9A-F]{1,6})$/i',
		$range, $m)
	) {
		for ($i = "0x{$m[1]}"; $i <= "0x{$m[2]}"; ++$i) {
			$hex = sprintf('%04x', $i);
			$s[] = "($i, CAST(_ucs2 x'$hex' AS CHAR CHARACTER SET utf8), '$hex')";
			if (count($s) >= $rows_per_insert) {
				$db->query($e = $ins . implode(',', $s) . ";");
				$s = array();
			}
		}
	}
}
if ($s) {
	$db->query($e = $ins . implode(',', $s) . ";");
}

// Now the interesting bit. Use mysql's GROUP BY to group rows of characters
// according to the collation. Use GROUP_CONCAT to get each set of chars the
// collation considers equivalent as:
//	  x: a comma separated list of utf8 characters
//	  y: a comma separated list of hex unicode codepoints
$r = $db->query(
	"SELECT GROUP_CONCAT(`mychar` ORDER BY `dec` ASC SEPARATOR ',') AS x,
		GROUP_CONCAT(`hex` ORDER BY `dec` ASC SEPARATOR ',') AS y
		FROM $tablename GROUP BY `mychar`;"
);

// For each grouped set, write to stdout each column x and y as two comma-
// separated lists with a tab in between
if ($r) {
	foreach ($r as $row) {
		print($row['x'] . "\t" . $row['y'] . "\n");
	}
}


/**
 * Interactively prompts for input without echoing to the terminal.
 * Requires a bash shell or Windows and won't work with
 * safe_mode settings (Uses `shell_exec`)
 * @param string @prompt password entry prompt
 * @return mixed|string the interactively entered password
 */
function prompt_silent($prompt = "Enter Password:") {
	if (preg_match('/^win/i', PHP_OS)) {
		$vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
		file_put_contents(
			$vbscript, 'wscript.echo(InputBox("'
			. addslashes($prompt)
			. '", "", "password here"))');
		$command = "cscript //nologo " . escapeshellarg($vbscript);
		$password = rtrim(shell_exec($command));
		unlink($vbscript);
		return $password;
	} else {
		$command = "/usr/bin/env bash -c 'echo OK'";
		if (rtrim(shell_exec($command)) !== 'OK') {
			trigger_error("Can't invoke bash");
			return false;
		}
		$command = "/usr/bin/env bash -c 'read -s -p \""
			. addslashes($prompt)
			. "\" mypassword && echo \$mypassword'";
		$password = rtrim(shell_exec($command));
		echo "\n";
		return $password;
	}
}
