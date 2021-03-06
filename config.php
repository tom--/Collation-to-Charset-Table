<?php

// Overrides the default values of public properties of CollationToCharsetTable.
// See CollationToCharsetTable's property doc-blocks for documentation.
return [
    'pdoDsn' => 'mysql:host=127.0.0.1',
    'pdoUser' => 'root',
    'pdoPass' => require __DIR__ . '/pdo_password.php',

    'maxFoldRun' => null,
    'collateRanges' => [
        [0x0000, 0x007F],
        [0x0080, 0x00FF],
        [0x0100, 0x024F],
        //[0x0250, 0x036F],
        //[0x0370, 0x04FF],
        //[0x0500, 0x08FF]
        //[0x0900, 0x1FFF],
        //[0x2000, 0x33FF],
        //[0xA000, 0xABCF],
        //[0xF900, 0xFFFF],
    ],
    'appendRanges' => [
        //[0x3400, 0x9FFF],
        //[0xAC00, 0xD7FF],
        //[0x10000, 0x1FFFF],
    ],
    'excludeGeneralCategories' => [
        \IntlChar::CHAR_CATEGORY_UNASSIGNED,
        //\IntlChar::CHAR_CATEGORY_UPPERCASE_LETTER,
        //\IntlChar::CHAR_CATEGORY_LOWERCASE_LETTER,
        //\IntlChar::CHAR_CATEGORY_TITLECASE_LETTER,
        //\IntlChar::CHAR_CATEGORY_MODIFIER_LETTER,
        //\IntlChar::CHAR_CATEGORY_OTHER_LETTER,
        //\IntlChar::CHAR_CATEGORY_NON_SPACING_MARK,
        //\IntlChar::CHAR_CATEGORY_ENCLOSING_MARK,
        //\IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK,
        //\IntlChar::CHAR_CATEGORY_DECIMAL_DIGIT_NUMBER,
        //\IntlChar::CHAR_CATEGORY_LETTER_NUMBER,
        //\IntlChar::CHAR_CATEGORY_OTHER_NUMBER,
        \IntlChar::CHAR_CATEGORY_SPACE_SEPARATOR,
        \IntlChar::CHAR_CATEGORY_LINE_SEPARATOR,
        \IntlChar::CHAR_CATEGORY_PARAGRAPH_SEPARATOR,
        \IntlChar::CHAR_CATEGORY_CONTROL_CHAR,
        \IntlChar::CHAR_CATEGORY_FORMAT_CHAR,
        \IntlChar::CHAR_CATEGORY_PRIVATE_USE_CHAR,
        \IntlChar::CHAR_CATEGORY_SURROGATE,
        \IntlChar::CHAR_CATEGORY_DASH_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_START_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_END_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_CONNECTOR_PUNCTUATION,
        \IntlChar::CHAR_CATEGORY_OTHER_PUNCTUATION,
        //\IntlChar::CHAR_CATEGORY_MATH_SYMBOL,
        //\IntlChar::CHAR_CATEGORY_CURRENCY_SYMBOL,
        //\IntlChar::CHAR_CATEGORY_MODIFIER_SYMBOL,
        //\IntlChar::CHAR_CATEGORY_OTHER_SYMBOL,
        //\IntlChar::CHAR_CATEGORY_INITIAL_PUNCTUATION,
        //\IntlChar::CHAR_CATEGORY_FINAL_PUNCTUATION,
    ],
    'excludeBinaryProperties' => [
        //\IntlChar::PROPERTY_ASCII_HEX_DIGIT,
        //\IntlChar::PROPERTY_ALPHABETIC,
        //\IntlChar::PROPERTY_BIDI_CONTROL,
        //\IntlChar::PROPERTY_BIDI_MIRRORED,
        //\IntlChar::PROPERTY_CASED,
        //\IntlChar::PROPERTY_FULL_COMPOSITION_EXCLUSION,
        //\IntlChar::PROPERTY_CASE_IGNORABLE,
        //\IntlChar::PROPERTY_FULL_COMPOSITION_EXCLUSION,
        //\IntlChar::PROPERTY_CHANGES_WHEN_CASEFOLDED,
        //\IntlChar::PROPERTY_CHANGES_WHEN_CASEMAPPED,
        //\IntlChar::PROPERTY_CHANGES_WHEN_NFKC_CASEFOLDED,
        //\IntlChar::PROPERTY_CHANGES_WHEN_LOWERCASED,
        //\IntlChar::PROPERTY_CHANGES_WHEN_TITLECASED,
        //\IntlChar::PROPERTY_CHANGES_WHEN_UPPERCASED,
        \IntlChar::PROPERTY_DASH,
        //\IntlChar::PROPERTY_DEPRECATED,
        //\IntlChar::PROPERTY_DEFAULT_IGNORABLE_CODE_POINT,
        //\IntlChar::PROPERTY_DIACRITIC,
        //\IntlChar::PROPERTY_EXTENDER,
        //\IntlChar::PROPERTY_GRAPHEME_BASE,
        //\IntlChar::PROPERTY_GRAPHEME_EXTEND,
        //\IntlChar::PROPERTY_GRAPHEME_LINK,
        //\IntlChar::PROPERTY_HEX_DIGIT,
        \IntlChar::PROPERTY_HYPHEN,
        //\IntlChar::PROPERTY_ID_CONTINUE,
        //\IntlChar::PROPERTY_IDEOGRAPHIC,
        //\IntlChar::PROPERTY_ID_START,
        //\IntlChar::PROPERTY_IDS_BINARY_OPERATOR,
        //\IntlChar::PROPERTY_IDS_TRINARY_OPERATOR,
        //\IntlChar::PROPERTY_JOIN_CONTROL,
        //\IntlChar::PROPERTY_LOGICAL_ORDER_EXCEPTION,
        //\IntlChar::PROPERTY_LOWERCASE,
        //\IntlChar::PROPERTY_MATH,
        \IntlChar::PROPERTY_NONCHARACTER_CODE_POINT,
        //\IntlChar::PROPERTY_PATTERN_SYNTAX,
        \IntlChar::PROPERTY_PATTERN_WHITE_SPACE,
        \IntlChar::PROPERTY_QUOTATION_MARK,
        //\IntlChar::PROPERTY_RADICAL,
        //\IntlChar::PROPERTY_SOFT_DOTTED,
        \IntlChar::PROPERTY_TERMINAL_PUNCTUATION,
        //\IntlChar::PROPERTY_UNIFIED_IDEOGRAPH,
        //\IntlChar::PROPERTY_UPPERCASE,
        //\IntlChar::PROPERTY_VARIATION_SELECTOR,
        \IntlChar::PROPERTY_WHITE_SPACE,
        //\IntlChar::PROPERTY_XID_CONTINUE,
        //\IntlChar::PROPERTY_XID_START,
    ],
];
