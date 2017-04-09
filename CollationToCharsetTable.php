<?php
declare(strict_types=1);

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

namespace spinitron\c2ct;

class CollationToCharsetTable
{
    /**
     * @var string Name of the working database to use for processing.
     */
    public $dbname = 'collation_2_charset_table';
    /**
     * WARNING The script drops and creates this table on each run!
     * @var string Name of the working table to use for processing.
     */
    public $tablename = 'collation_2_charset_table';
    /**
     * @var string Your choice of character encoding, probably "utf8" or "utf8mb4"
     */
    public $charset = 'utf8mb4';
    /**
     * @var string Collation to use
     */
    public $collation = 'utf8mb4_general_ci';
    /**
     * @var string PDO DSN
     */
    public $pdo_dsn = 'mysql:host=127.0.0.1';
    /**
     * @var string MySQL user name
     */
    public $pdo_user = 'root';
    /**
     * @var string MySQL password. Change this to set your password or add a file
     * named "pdo_password.php" that returns it.
     */
    public $pdo_pass = 'passwerd';

    /**
     * Codepoint ranges to include in a Sphinx charset_table.
     *
     * See http://sphinxsearch.com/docs/current.html#conf-charset-table
     *
     * collation_2_charset_table generates a charset_table that includes Unicode
     * codepoints the ranges in this array. Codepoints that do not appear in these
     * ranges cannot be in Sphinx-indexed keywords and will function as keyword
     * separators.
     *
     * If the range value is true then the charset_table folds codepoints in the
     * range according to your chosen collation.
     *
     * Characters that you want to function as keyword separators must be educed
     * from the ranges by any of the following:
     *
     * 1. excluded their codepoints from $ranges, e.g. if U+20 doesn't appear in any
     * range in $ranges then it is a separator.
     *
     * 2. If you have PHP 7, ICU and Intl extension then you can additionally exclude
     * on the basis of character category and/or property. See below.
     *
     * 3. Manually edit the output of collation_2_charset_table-1.php before feeding
     * it to collation_2_charset_table-1.php.
     *
     * @var string[] Array of hex ranges as strings
     */
    public $ranges = [];

    /**
     * @var int[] Character categories to exclude
     */
    public $exclude_character_categories = [];

    /**
     * @var int[] Character properties to exclude
     */
    public $exclude_properties = [];

    public function __construct(array $config = [])
    {
        foreach ($config as $name => $value) {
            (new \ReflectionProperty(static::class, $name))->setValue($this, $value);
        }
    }

    /**
     * Populate the MySQL DB table according to configured ranges and exclusions.
     */
    public function newTable()
    {
        $db = new \PDO($this->pdo_dsn, $this->pdo_user, $this->pdo_pass);
        $db->query("SET NAMES '$this->charset' COLLATE '$this->collation';");
        $db->query("CREATE DATABASE IF NOT EXISTS `$this->dbname`;");

        $db->query("DROP TABLE IF EXISTS `$this->dbname`.`$this->tablename`;");
        $db->query(
            "CREATE TABLE IF NOT EXISTS `$this->dbname`.`$this->tablename` (
                `dec` int NOT NULL,
                `mychar` char(1) CHARACTER SET $this->charset COLLATE $this->collation NOT NULL,
                `hex` varchar(8) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                PRIMARY KEY  (`dec`)
            );"
        );

        $statement = $db->prepare(
            "INSERT IGNORE INTO `$this->dbname`.`$this->tablename` 
            (`dec`, `mychar`, `hex`) VALUES (:dec, :chr, :hex)"
        );
        $categoryLookup = [
            0 => 'UNASSIGNED/GENERAL_OTHER_TYPES',
            \IntlChar::CHAR_CATEGORY_UPPERCASE_LETTER => 'UPPERCASE_LETTER',
            \IntlChar::CHAR_CATEGORY_LOWERCASE_LETTER => 'LOWERCASE_LETTER',
            \IntlChar::CHAR_CATEGORY_TITLECASE_LETTER => 'TITLECASE_LETTER',
            \IntlChar::CHAR_CATEGORY_MODIFIER_LETTER => 'MODIFIER_LETTER',
            \IntlChar::CHAR_CATEGORY_OTHER_LETTER => 'OTHER_LETTER',
            \IntlChar::CHAR_CATEGORY_NON_SPACING_MARK => 'NON_SPACING_MARK',
            \IntlChar::CHAR_CATEGORY_ENCLOSING_MARK => 'ENCLOSING_MARK',
            \IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK => 'COMBINING_SPACING_MARK',
            \IntlChar::CHAR_CATEGORY_DECIMAL_DIGIT_NUMBER => 'DECIMAL_DIGIT_NUMBER',
            \IntlChar::CHAR_CATEGORY_LETTER_NUMBER => 'LETTER_NUMBER',
            \IntlChar::CHAR_CATEGORY_OTHER_NUMBER => 'OTHER_NUMBER',
            \IntlChar::CHAR_CATEGORY_SPACE_SEPARATOR => 'SPACE_SEPARATOR',
            \IntlChar::CHAR_CATEGORY_LINE_SEPARATOR => 'LINE_SEPARATOR',
            \IntlChar::CHAR_CATEGORY_PARAGRAPH_SEPARATOR => 'PARAGRAPH_SEPARATOR',
            \IntlChar::CHAR_CATEGORY_CONTROL_CHAR => 'CONTROL_CHAR',
            \IntlChar::CHAR_CATEGORY_FORMAT_CHAR => 'FORMAT_CHAR',
            \IntlChar::CHAR_CATEGORY_PRIVATE_USE_CHAR => 'PRIVATE_USE_CHAR',
            \IntlChar::CHAR_CATEGORY_SURROGATE => 'SURROGATE',
            \IntlChar::CHAR_CATEGORY_DASH_PUNCTUATION => 'DASH_PUNCTUATION',
            \IntlChar::CHAR_CATEGORY_START_PUNCTUATION => 'START_PUNCTUATION',
            \IntlChar::CHAR_CATEGORY_END_PUNCTUATION => 'END_PUNCTUATION',
            \IntlChar::CHAR_CATEGORY_CONNECTOR_PUNCTUATION => 'CONNECTOR_PUNCTUATION',
            \IntlChar::CHAR_CATEGORY_OTHER_PUNCTUATION => 'OTHER_PUNCTUATION',
            \IntlChar::CHAR_CATEGORY_MATH_SYMBOL => 'MATH_SYMBOL',
            \IntlChar::CHAR_CATEGORY_CURRENCY_SYMBOL => 'CURRENCY_SYMBOL',
            \IntlChar::CHAR_CATEGORY_MODIFIER_SYMBOL => 'MODIFIER_SYMBOL',
            \IntlChar::CHAR_CATEGORY_OTHER_SYMBOL => 'OTHER_SYMBOL',
            \IntlChar::CHAR_CATEGORY_INITIAL_PUNCTUATION => 'INITIAL_PUNCTUATION',
            \IntlChar::CHAR_CATEGORY_FINAL_PUNCTUATION => 'FINAL_PUNCTUATION',
            \IntlChar::CHAR_CATEGORY_CHAR_CATEGORY_COUNT => 'CHAR_CATEGORY_COUNT',
        ];

        foreach ($this->ranges as $range) {
            list($from, $to) = $range;
            foreach (range($from, $to) as $codepoint) {
                // Exclude 0x00-0x1F control characters and 0x20 space regardless of input ranges.
                // (because \t, \n, and space are formatting characters for the editable table.)
                $charType = \IntlChar::charType($codepoint);
                if ($codepoint <= 32 || in_array($charType, $this->exclude_character_categories, true)
                ) {
                    if ($codepoint > 0x10000 && $charType > 0) {
                        printf(
                            "%s  %05X  %s : (%s) %s\n",
                            \IntlChar::chr($codepoint),
                            $codepoint,
                            \IntlChar::charName($codepoint),
                            var_export($charType, true),
                            $categoryLookup[$charType]
                        );
                    }

                    continue;
                }

                foreach ($this->exclude_properties as $property) {
                    if (\IntlChar::hasBinaryProperty($codepoint, $property)) {
                        if ($codepoint > 0x10000) {
                            printf(
                                "%s  %05X  %s - [%s] %s\n",
                                \IntlChar::chr($codepoint),
                                $codepoint,
                                \IntlChar::charName($codepoint),
                                var_export($property, true),
                                \IntlChar::getPropertyName($property)
                            );
                        }

                        continue;
                    }
                }

                $statement->execute([
                    ':dec' => $codepoint,
                    ':chr' => \IntlChar::chr($codepoint),
                    ':hex' => sprintf('%X', $codepoint),
                ]);

                $warnings = $db->query('show warnings;');
                if ($warnings) {
                    foreach ($warnings as $warning) {
                        var_dump($warning);
                    }
                }
            }
        }
    }

    /** @var array [[[character, ...], [codepoint, ...]], ...] */
    private $collated;

    protected function getCollatedCodepoints(): array
    {
        if ($this->collated === null) {
            $db = new \PDO($this->pdo_dsn, $this->pdo_user, $this->pdo_pass);

            // Now the interesting bit. Use mysql's GROUP BY to group rows of characters
            // according to the collation. Use GROUP_CONCAT to get each set of chars the
            // collation considers equivalent as:
            //	  x: a space-separated list of UTF-8 characters
            //	  y: a space-separated list of hex unicode codepoints
            $rows = $db->query(
                "SELECT 
                GROUP_CONCAT(`mychar` ORDER BY `dec` ASC SEPARATOR 0x01) AS `characters`,
                GROUP_CONCAT(`dec` ORDER BY `dec` ASC SEPARATOR 0x01) AS `codepoints`
                FROM `$this->dbname`.`$this->tablename` 
                GROUP BY `mychar`;"
            );

            $this->collated = [];
            // For each grouped set, write to stdout each column x and y as two space-
            // separated lists with a tab in between
            foreach ($rows as $row) {
                $this->collated[] = [
                    explode(chr(0x01), $row['characters']),
                    array_map(function ($value) {
                        return (int)$value;
                    }, explode(chr(0x01), $row['codepoints'])),
                ];
            }
        }

        return $this->collated;
    }

    public function parseEditableTable(string $editableTable, $hex = true)
    {
        $this->collated = [];
        foreach (explode("\n", $editableTable) as $line) {
            list($characters, $codepoints) = explode("\t", $line);
            $this->collated[] = [
                explode(' ', $characters),
                array_map(function ($value) use ($hex) {
                    return (int)($hex ? hexdec($value) : $value);
                }, explode(' ', $codepoints)),
            ];
        }
    }

    protected function getBlendedRules()
    {
        list($strays, $folds) = $this->getStraysAndFolds();

        return $this->blendCharsetRules($this->rangeCodepoints($strays), $folds);
    }

    /**
     * @return array [[stray, ...], [foldTo => [fold, ...], ...]]
     */
    protected function getStraysAndFolds(): array
    {
        $strays = [];
        $folds = [];

        foreach ($this->getCollatedCodepoints() as list($characters, $codepoints)) {
            $strays[] = $codepoints[0];
            if (count($codepoints) > 1) {
                $folds[array_shift($codepoints)] = $codepoints;
            }
        }

        return [$strays, $folds];
    }

    /**
     * Encode runs of 3 or more codepoints into [from, to] doubles representing ranges,
     * e.g. [40, 45, 46, 47, 49] becomes [40, [45, 47], 49]
     * @param int[] $codepoints array of codepoints
     * @return mixed[] array of codepoints
     */
    protected function rangeCodepoints(array $codepoints): array
    {
        sort($codepoints);

        $i = 0;
        do {
            if ($codepoints[$i] + 1 === ($codepoints[$i + 1] ?? 0)
                && $codepoints[$i] + 2 === ($codepoints[$i + 2] ?? 0)
            ) {
                $runStart = $codepoints[$i];
                do {
                    $i += 1;
                } while ($codepoints[$i] + 1 === ($codepoints[$i + 1] ?? 0));
                $ranged[] = [$runStart, $codepoints[$i]];
            } else {
                $ranged[] = $codepoints[$i];
            }
            $i += 1;
        } while (isset($codepoints[$i]));

        return $ranged;
    }

    /**
     * @param $rangedCodepoints array of codepoints and [from, to] ranges
     * @param $folds array of folds as [foldTo => [fold, ...], ...]
     * @return array all elements from both input arrays arranged in some suitable order
     */
    protected function blendCharsetRules($rangedCodepoints, $folds): array
    {
        $blended = [];
        foreach ($rangedCodepoints as $item) {
            $blended[] = $item;
            foreach (is_array($item) ? range($item[0], $item[1]) : [$item] as $codepoint) {
                if (isset($folds[$codepoint])) {
                    $blended[] = [$codepoint, $folds[$codepoint]];
                }
            }
        }

        return $blended;
    }

    protected function u(int $codepoint)
    {
        return $codepoint > 32 && $codepoint < 127 ? chr($codepoint) : sprintf('U+%02x', $codepoint);
    }

    protected function utf8Folds(array $folds, string $foldSeparator = ' ', $ruleSeparator = ' ← '): string
    {
        return \IntlChar::chr($folds[0]) . $ruleSeparator
            . implode($foldSeparator, array_map(function ($fold) use ($folds) {
                return \IntlChar::chr($fold);
            }, $folds[1]));
    }

    protected function utf8Table(array $blendedTable, string $indentFolds = "     ", $rangeSeparator = ' … '): array
    {
        $outputLines = [];
        foreach ($blendedTable as $item) {
            $outputLines[] = is_int($item)
                ? \IntlChar::chr($item)
                : (is_array($item[1])
                    ? $indentFolds . $this->utf8Folds($item)
                    : \IntlChar::chr($item[0]) . $rangeSeparator . \IntlChar::chr($item[1]));
        }

        return $outputLines;
    }

    protected function sphinxFolds(array $folds, string $separator = ','): string
    {
        return implode($separator, array_map(function ($fold) use ($folds) {
            return $this->u($fold) . '->' . $this->u($folds[0]);
        }, $folds[1]));
    }

    protected function sphinxStrayRange(array $range): string
    {
        return $this->u($range[0]) . '..' . $this->u($range[1]);
    }

    protected function sphinxStray(int $codepoint): string
    {
        return $this->u($codepoint);
    }

    protected function readableSphinxTable(array $blendedTable, string $indentFolds = "   "): array
    {
        $outputLines = [];
        foreach ($blendedTable as $item) {
            $outputLines[] = is_int($item)
                ? $this->sphinxStray($item)
                : (is_array($item[1])
                    ? $indentFolds . $this->sphinxFolds($item)
                    : $this->sphinxStrayRange($item));
        }

        return $outputLines;
    }

    protected function compressSphinxTable(
        array $blendedTable,
        int $wrap = 120,
        string $separator = ', ',
        int $indent = 8,
        int $first = 4
    ): array {
        $rules = [];
        foreach ($blendedTable as $item) {
            if (is_array($item)) {
                if (is_array($item[1])) {
                    foreach ($item[1] as $fold) {
                        $rules[] = $this->u($fold) . '->' . $this->u($item[0]);
                    }
                } else {
                    $rules[] = $this->sphinxStrayRange($item);
                }
            } else {
                $rules[] = $this->sphinxStray($item);
            }
        }

        $lines = [];
        $line = str_repeat(' ', $first) . 'charset_table = ' . array_shift($rules);
        foreach ($rules as $rule) {
            if (strlen(rtrim($line . $separator . $rule)) > $wrap) {
                $line = rtrim($line . $separator);
                $lines[] = $line;
                $line = str_repeat(' ', $indent) . $rule;
            } else {
                $line .= $separator . $rule;
            }
        }
        $lines[] = $line;

        return $lines;
    }

    public function getEditable($hex = true, $sort = true)
    {
        $collatedCodepoints = $this->getCollatedCodepoints();
        if ($sort) {
            usort($collatedCodepoints, function ($a, $b) {
                return $a[1][0] <=> $b[1][0];
            });
        }

        $lines = [];
        foreach ($collatedCodepoints as list($characters, $codepoints)) {
            if ($hex) {
                $codepoints = array_map('dechex', $codepoints);
            }
            $lines[] = implode(' ', $characters) . "\t" . implode(' ', $codepoints);
            echo implode(' ', $codepoints) . "\n";
        }
exit;
        return implode("\n", $lines);
    }

    public function getUtf8(): string
    {
        return implode("\n", $this->utf8Table($this->getBlendedRules())) . "\n";
    }

    public function getSphinx(): string
    {
        return implode(",\n", $this->readableSphinxTable($this->getBlendedRules())) . "\n";
    }

    public function getRules(): string
    {
        return implode("\n", $this->compressSphinxTable($this->getBlendedRules())) . "\n";
    }
}
