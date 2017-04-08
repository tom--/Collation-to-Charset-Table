<?php

// Overrides the default values of public properties of CollationToCharsetTable
// if you pass it to the constructor.
return [
    'pdo_dsn' => 'mysql:host=127.0.0.1',
    'pdo_user' => 'root',
    'pdo_pass' => require __DIR__ . '/pdo_password.php',

    'ranges' => [
        '0021-007E' => true,
        '00A0-02AF' => true,
        '02B0-036F' => false,
        '0370-058F' => true,    // Latin, Latin Ext., Greek, Coptic, Cyrillic etc.
        '0590-109F' => false,
        '10A0-10FF' => true,    // Georgian
        '1100-167F' => false,
        '1700-1DFF' => false,
        '1E00-1FFF' => true,    // Latin Ext. Additional, Greek Ext.
        '2070-2BFF' => false,
        '2C00-2D2F' => true,    // Glagolitic, Latin Ext.-C, Coptic, Georgian Sup.
        '2D30-2DDF' => false,
        '2DE0-2DFF' => true,   // Cyrillic Ext.-A
        '2E00-A63F' => false,
        'A640-A7FF' => true,   // Cyrillic Ext.-B, ..., Latin Ext.-D
        'A800-D7FF' => false,
        'E000-2FFFF' => false,
    ],
    'exclude_character_categories' => [
        \IntlChar::CHAR_CATEGORY_UNASSIGNED,
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
