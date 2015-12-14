<?php

/*
 * Everything in the 'collate' ranges is included for Sphinx indexing, regardless of the 'exclude' ranges.
 * Character folding rules the 'collate' ranges are generated according to 'charset' and 'collation'.
 * Finally, eveything in Unicode (U+0000..U+10FFFF) minus the 'exclude' ranges is included for
 * indxing without regard to collation.
 */

return [
    // MySQL charset name, either utf8 or utf8mb4
    'charset' => 'utf8mb4',
    // Collation to use with the charset
    'collation' => 'utf8mb4_general_ci',
    // Unicode ranges to include in indexes and fold according to 'collation'. Individual
    // code points are allowed, e.g. ['261C']
    'collate' => [
        ['0000', '007E'],
        ['00A0', '02AF'],
        ['0370', '058F'],
        ['10A0', '10FF'],
        ['1D00', '1FFF'],
        ['2460', '24FF'],
        ['2C60', '2C7F'],
        ['2D00', '2D2F'],
        ['A4D0', 'A4F0'],
        ['A720', 'A7F0'],
        ['FF00', 'FFEF'],
    ],
    // Ranges to exclude from indexes. In case of overlap, 'collate' has precidence over 'exclude'.
    'exclude' => [
        ['007F', '009F'],
        ['D800', 'DBFF'],
        ['DC00', 'DFFF'],
        ['E000', 'F8FF'],
        ['30000', '10FFFF'],
    ],
    // Name of the working database to use for processing
    'dbname' => 'my_collation_db',
    // Name of the working table to use for processing
    'tablename' => 'my_table',
    // PDO DSN
    'pdoDsn' => 'mysql:host=127.0.0.1',
    // MySQL user name
    'mysqlUser' => 'root',
    // MySQL password. Change to a string literal if you prefer.
    'mysqlPass' => include(__DIR__ . '/mysql_password.php'),
];
