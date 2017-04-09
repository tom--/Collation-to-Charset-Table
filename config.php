<?php

// Overrides the default values of public properties of CollationToCharsetTable
// if you pass it to the constructor.
return [
    'pdo_dsn' => 'mysql:host=127.0.0.1',
    'pdo_user' => 'root',
    'pdo_pass' => require __DIR__ . '/pdo_password.php',

    'ranges' => [
        [0x0021, 0x007E],
        //[0x00A0, 0x1FFF],
        //[0x2070, 0xA7FF],
        //[0xA800, 0xD7FF],
        //[0xE000, 0xEFFF],
        //[0xF000, 0xFFFF],
        //[0x10000, 0x1BFFF],
        [0x1D000, 0x1F1FF],
        [0x1F300, 0x1F9FF],
    ],
    'exclude_character_categories' => [
        //\IntlChar::CHAR_CATEGORY_UNASSIGNED,
        \IntlChar::CHAR_CATEGORY_SPACE_SEPARATOR,
        \IntlChar::CHAR_CATEGORY_LINE_SEPARATOR,
        \IntlChar::CHAR_CATEGORY_PARAGRAPH_SEPARATOR,
        \IntlChar::CHAR_CATEGORY_CONTROL_CHAR,
        \IntlChar::CHAR_CATEGORY_FORMAT_CHAR,
        \IntlChar::CHAR_CATEGORY_SURROGATE,
        \IntlChar::CHAR_CATEGORY_DASH_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_START_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_END_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_CONNECTOR_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_OTHER_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_INITIAL_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_FINAL_PUNCTUATION,
    ],
    'exclude_properties' => [
        \IntlChar::PROPERTY_DASH,
        \IntlChar::PROPERTY_DEFAULT_IGNORABLE_CODE_POINT,
        \IntlChar::PROPERTY_HYPHEN,
        \IntlChar::PROPERTY_TERMINAL_PUNCTUATION,
        \IntlChar::PROPERTY_WHITE_SPACE,
        \IntlChar::PROPERTY_PATTERN_WHITE_SPACE,
        \IntlChar::PROPERTY_POSIX_BLANK,
        \IntlChar::PROPERTY_LINE_BREAK,
        \IntlChar::PROPERTY_SENTENCE_BREAK,
        \IntlChar::PROPERTY_WORD_BREAK,
        \IntlChar::PROPERTY_INVALID_CODE,
    ],
];
