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
    ['0000', '02AF'],
    ['0370', '058F'],
    ['10A0', '10FF'],
    ['1E00', '1FFF'],
    ['1F300', '1F77F'],
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
 * @var string MySQL charset name, either utf8 or utf8mb4
 */
$charset = 'utf8mb4';
/**
 * @var string Collation to use with the charset
 */
$collation = 'utf8mb4_general_ci';
/**
 * @var string PDO DSN
 */
$pdoDsn = 'mysql:host=127.0.0.1';
/**
 * @var string MySQL user name
 */
$mysqlUser = 'user';
/**
 * @var string MySQL password
 */
$mysqlPass = 'password';
/*
 * END OF USER CONFIGURATIONS
 * ============================================================================
 */

ini_set('default_charset', 'UTF-8');

function myPdoError(PDO $db, $query)
{
    fwrite(STDERR, 'PDO eorror: (' . $db->errorCode() . ') ' . var_export($db->errorInfo(), true) . "\n");
    fwrite(STDERR, 'Doing: ' . var_export($query, true) . "\n");

    exit(1);
}

function myQuery(PDO $db, $query)
{
    $result = $db->query($query);
    if ($result === false) {
        myPdoError($db, $query);
    }

    return $result;
}

$db = new PDO($pdoDsn, $mysqlUser, $mysqlPass);
myQuery($db, "SET NAMES $charset COLLATE $collation");
myQuery($db, "DROP DATABASE IF EXISTS `$dbname`");
myQuery($db, "CREATE DATABASE `$dbname`");
myQuery($db, "USE $dbname");

myQuery($db,
    "CREATE TABLE `$tablename` (
        `dec` int NOT NULL,
        `mychar` char(1) CHARACTER SET $charset COLLATE $collation NOT NULL,
        `hex` varchar(8) NOT NULL,
        PRIMARY KEY  (`dec`)
    ) ENGINE=MyISAM;"
);

$statement = $db->prepare(
    "INSERT IGNORE INTO `$tablename` (`dec`, `hex`, `mychar`) VALUES (:dec, :hex, :mychar)"
);
$statement->bindParam(':dec', $dec);
$statement->bindParam(':hex', $hex);
$statement->bindParam(':mychar', $mychar);

// Add a row to the working table for every Unicode char in the ranges specified
foreach ($ranges as $range) {
    for ($dec = "0x{$range[0]}"; $dec <= "0x{$range[1]}"; $dec += 1) {
        $hex = sprintf('%04x', $dec);
        $mychar = mb_convert_encoding(hex2bin(sprintf('%08x', $dec)), 'UTF-8', 'UTF-32BE');
        if ($statement->execute() === false) {
            myPdoError($db, $statement);
        }
    }
}

// Now the interesting bit. Use mysql's GROUP BY to group rows of characters
// according to the collation. Use GROUP_CONCAT to get each set of chars the
// collation considers equivalent as:
//	  x: a comma separated list of utf8 characters
//	  y: a comma separated list of hex unicode codepoints
$r = myQuery($db,
    "SELECT GROUP_CONCAT(`mychar` ORDER BY `dec` ASC SEPARATOR ',') AS x,
            GROUP_CONCAT(`hex`    ORDER BY `dec` ASC SEPARATOR ',') AS y
    FROM $tablename GROUP BY `mychar`;"
);

// For each grouped set, write to stdout each column x and y as two comma-
// separated lists with a tab in between
if ($r) {
    foreach ($r as $row) {
        print($row['x'] . "\t" . $row['y'] . "\n");
    }
}
