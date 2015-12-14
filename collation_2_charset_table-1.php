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

$config = require(__DIR__ . '/config.php');

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

$db = new PDO($config['pdoDsn'], $config['mysqlUser'], $config['mysqlPass']);
myQuery($db, "SET NAMES {$config['charset']} COLLATE {$config['collation']}");
myQuery($db, "DROP DATABASE IF EXISTS `{$config['dbname']}`");
myQuery($db, "CREATE DATABASE `{$config['dbname']}`");
myQuery($db, "USE `{$config['dbname']}`");

myQuery($db,
    "CREATE TABLE `{$config['tablename']}` (
        `dec` int NOT NULL,
        `mychar` char(1) CHARACTER SET {$config['charset']} COLLATE {$config['collation']} NOT NULL,
        `hex` varchar(5) NOT NULL,
        PRIMARY KEY  (`dec`)
    ) ENGINE=MyISAM;"
);

$statement = $db->prepare(
    "INSERT IGNORE INTO `{$config['tablename']}` VALUES (:dec, :mychar, :hex)"
);
$statement->bindParam(':dec', $dec);
$statement->bindParam(':hex', $hex);
$statement->bindParam(':mychar', $mychar);

// Add a row to the working table for every Unicode char in the ranges specified
foreach ($config['collate'] as $range) {
    $from = hexdec($range[0]);
    $to = hexdec(end($range));
    for ($dec = $from; $dec <= $to; $dec += 1) {
        $hex = sprintf('%02x', $dec);
        $mychar = mb_convert_encoding(hex2bin(sprintf('%08x', $dec)), 'UTF-8', 'UTF-32BE');
        if ($statement->execute() === false) {
            myPdoError($db, $statement);
        }
    }
}

// Now the interesting bit. Use mysql's GROUP BY to group rows of characters
// according to the collation. Use GROUP_CONCAT to get each set of chars the
// collation considers equivalent as:
//	  x: a comma separated list of utf8 characters (or U+ code points for U+00 to U+20)
//	  y: a comma separated list of hex unicode code points
$rows = myQuery($db,
    "SELECT
      GROUP_CONCAT(if(ord(`mychar`) < 33, concat('U+', `hex`), `mychar`) ORDER BY `dec` ASC SEPARATOR ',') AS x,
      GROUP_CONCAT(`hex` ORDER BY `dec` ASC SEPARATOR ',') AS y
    FROM `{$config['tablename']}` GROUP BY `mychar`;"
);

// For each grouped set, write to stdout each column x and y as two comma-
// separated lists with a tab in between
foreach ($rows as $row) {
    print($row['x'] . "\t" . $row['y'] . "\n");
}
