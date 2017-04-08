<?php

namespace spinitron\c2ct;

require __DIR__ . '/CollationToCharsetTable.php';

echo (new CollationToCharsetTable(require __DIR__ . '/config.php'))
    ->getCharsetTable(file_get_contents('php://stdin'));
