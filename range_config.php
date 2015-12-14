<?php

// Everything in the collate ranges is indexed.
// Then, after collation processing, everything remaining minus
// the 'exclude' ranges is indexed.

return [
    // Include these Unicode ranges for indexing and generate Sphinx charset folding rules for them.
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
    // Exclude these unicode ranges from Sphinx indexing.
    'exclude' => [
        ['0080', '009F'],
        ['D800', 'DBFF'],
        ['DC00', 'DFFF'],
        ['E000', 'F8FF'],
        ['30000', '10FFFF'],
    ],
];
