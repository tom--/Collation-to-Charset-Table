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
    public $dbName = 'collation_2_charset_table';
    /**
     * WARNING The script drops and creates this table on each run!
     * @var string Name of the working table to use for processing.
     */
    public $tableName = 'collation_2_charset_table';
    /**
     * @var string Your choice of character encoding, probably "utf8" or "utf8mb4"
     */
    public $charset = 'utf8mb4';
    /**
     * @var string Collation to use
     */
    public $collation = 'utf8mb4_unicode_ci';
    /**
     * @var string PDO DSN
     */
    public $pdoDsn = 'mysql:host=127.0.0.1';
    /**
     * @var string MySQL user name
     */
    public $pdoUser = 'root';
    /**
     * @var string MySQL password. Change this to set your password or add a file
     * named "pdo_password.php" that returns it.
     */
    public $pdoPass = '';

    /**
     * Codepoint ranges to include in the Sphinx charset_table.
     *
     * See http://sphinxsearch.com/docs/current.html#conf-charset-table
     *
     * Characters that you want to function as keyword separators must be exclude from
     * the final charset_table. You can do this by any of the following
     *
     * 1. Excluded their codepoints from $ranges, e.g. if U+20 doesn't appear in any
     * range in $ranges then it is a separator.
     *
     * 2. Exclude on the basis of Unicode character category and/or property.
     * @see $excludeProperties and $excludeCharacterCategories below.
     *
     * 3. Manually edit the output of collation_2_charset_table-1.php before feeding
     * it to collation_2_charset_table-1.php.
     *
     * 4. Manually edit the output Sphinx charset_table.
     *
     * @var string[] Array of hex ranges as strings
     */
    public $collateRanges = [];

    /**
     * @var int Parameter for heuristic shortening of longs strings of single-character
     * mappings.
     *
     * For example, U+1F600 thru U+1F64F all collate together. Normally this would result
     * in the rules
     *
     *      U+1F600, U+1F601->U+1F600, U+1F602->U+1F600, ..., U+1F64F->U+1F600
     *
     * which is verbose and maybe unhelpful because you might not want all emoticons to
     * be equivalent in Sphinx searches.
     *
     * $maxFoldRun is set then any contiguous run of folded characters longer than this
     * is converted into a stray range, e.g. the above example becomes instead
     *
     *      U+1F600..U+1F64F
     */
    public $maxFoldRun = 8;

    /**
     * @var int[] Unicode character categories to exclude. See the IntlChar::CHAR_CATEGORY_*
     * constants in https://secure.php.net/manual/en/intlchar.chartype.php
     */
    public $excludeCharacterCategories = [];

    /**
     * @var int[] Unicode character properties to exclude. See the IntlChar::PROPERTY_*
     * constants in https://secure.php.net/manual/en/class.intlchar.php#intlchar.constants.property-alphabetic
     */
    public $excludeProperties = [];

    /** @var array [[[character, ...], [codepoint, ...]], ...] */
    private $charsetTable;

    /**
     * @param array $config Sets public properties of the new object
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $name => $value) {
            try {
                (new \ReflectionProperty(static::class, $name))->setValue($this, $value);
            } catch (\ReflectionException $exception) {
                echo "There is no configuration property named '$name'. Check your config\n";

                exit(1);
            }
        }
    }

    /**
     * Populate the MySQL DB table according to configured ranges and exclusions.
     */
    public function createDbCharsetTable(bool $verbose = false)
    {
        $db = new \PDO($this->pdoDsn, $this->pdoUser, $this->pdoPass);
        $db->query("SET NAMES '$this->charset' COLLATE '$this->collation';");
        $db->query("CREATE DATABASE IF NOT EXISTS `$this->dbName`;");

        $db->query("DROP TABLE IF EXISTS `$this->dbName`.`$this->tableName`;");
        $db->query(
            "CREATE TABLE IF NOT EXISTS `$this->dbName`.`$this->tableName` (
                `dec` int NOT NULL,
                `mychar` char(1) CHARACTER SET $this->charset COLLATE $this->collation NOT NULL,
                `hex` varchar(8) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                PRIMARY KEY  (`dec`)
            );"
        );

        $statement = $db->prepare(
            "INSERT IGNORE INTO `$this->dbName`.`$this->tableName` 
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

        $insTotal = 0;
        $excTotal = 0;
        foreach ($this->collateRanges as $range) {
            list($from, $to) = $range;

            printf("Range %05X to %05X, %d codepoints", $from, $to, $to - $from + 1);
            $insRange = 0;
            $excludedRange = 0;
            foreach (range($from, $to) as $codepoint) {
                // Exclude 0x00-0x1F control characters and 0x20 space regardless of input ranges.
                // (because \t, \n, and space are formatting characters for the editable table.)
                $charType = \IntlChar::charType($codepoint);
                $exclude = null;
                if ($codepoint <= 32 || in_array($charType, $this->excludeCharacterCategories, true)
                ) {
                    $exclude = "Category ($charType) {$categoryLookup[$charType]}";
                }
                foreach ($this->excludeProperties as $property) {
                    if (\IntlChar::hasBinaryProperty($codepoint, $property)) {
                        $exclude = "Property ($property) " . \IntlChar::getPropertyName($property);

                        break;
                    }
                }

                if ($verbose) {
                    printf(
                        "%-8s%04X    %-40s %s\n",
                        \IntlChar::chr($codepoint),
                        $codepoint,
                        \IntlChar::charName($codepoint),
                        $exclude ? "Excluded on $exclude" : ''
                    );
                }

                if ($exclude === null) {
                    $statement->execute([
                        ':dec' => $codepoint,
                        ':chr' => \IntlChar::chr($codepoint),
                        ':hex' => sprintf('%X', $codepoint),
                    ]);

                    $warnings = $db->query('show warnings;');
                    if ($warnings) {
                        foreach ($warnings as $warning) {
                            var_dump($warning);
                            echo "exiting\n";

                            exit(1);
                        }
                    }
                    $insRange += 1;
                    $insTotal += 1;
                } else {
                    $excludedRange += 1;
                    $excTotal += 1;
                }

            }
            printf(". Inserted %d, excluded %d\n", $insRange, $to - $from + 1 - $insRange);
        }
        printf("Done\nTotal %d codepoints, inserted %d, excluded %d\n", $insTotal + $excTotal, $insTotal, $excTotal);
    }

    /**
     * Parse a human-editable charset table into the internal charset table.
     * @param string $editableTable
     * @param bool $hex Set true for codepoints in hex, false for decimal
     */
    public function parseCharsetTable(string $editableTable, $hex = true)
    {
        $this->charsetTable = [];
        foreach (explode("\n", $editableTable) as $i => $line) {
            if (empty(trim($line))) {
                // silently skip blank lines and lines with only spaces
                continue;
            }

            $characters = [];

            // find the first tab on the line, if there is one
            $pos = mb_strpos($line, "\t", 0, 'UTF-8');
            if ($pos !== false) {
                // extract the substr before the tab
                list($before, $line) = explode("\t", $line, 2);
                // put any space-separated characters in $characters (they are ignored anyway)
                $characters = preg_split('{ +}', trim(mb_substr($before, $pos + 1, null, 'UTF-8')));
            }

            // strip comments from end of line
            $line = preg_replace('{#.*$}', '', $line);

            // extract one or more space-separated hex numbers. U+ or 0x etc prefixes not allowed
            preg_match('{([0-9A-F]{1,8})(?: +([0-9A-F]{1,8}))*}i', $line, $matches);
            if (empty($matches)) {
                // mention lines with no codepoints
                fwrite(STDERR, 'No codepoints found on line ' . ($i + 1) . "\n");

                continue;
            }

            array_shift($matches);
            $codepoints = array_map('hexdec', $matches);

            $this->charsetTable[] = [$characters, $codepoints];
        }
    }

    /**
     * Return the internal charset table as previously parsed or from the DB charset table
     * @return array of sets of equally collated characters each set represented as
     * an array [characters, codepoints], where caracters is an array of utf8 chars
     * and codepoints is an array of integer codepoints, i.e.
     *
     *      [
     *          [[char, ...], [codepoint, ...]],
     *          ...
     *      ]
     */
    protected function getCharsetTable(): array
    {
        if ($this->charsetTable === null) {
            $this->charsetTable = $this->getCollatedFromDb();
        }

        return $this->charsetTable;
    }

    /**
     * Returns the DB charset table in internal charset format.
     * @return array @see getCharsetTable()
     */
    protected function getCollatedFromDb(): array
    {
        $db = new \PDO($this->pdoDsn, $this->pdoUser, $this->pdoPass);
        $db->query('SET SESSION group_concat_max_len = 10000000;');

        // Now the interesting bit. Use mysql's GROUP BY to group rows of characters
        // according to the collation. Use GROUP_CONCAT to get each set of chars the
        // collation considers equivalent as:
        //	  x: a space-separated list of UTF-8 characters
        //	  y: a space-separated list of hex unicode codepoints
        $rows = $db->query(
            "SELECT 
                GROUP_CONCAT(`mychar` ORDER BY `dec` ASC SEPARATOR 0x01) AS `characters`,
                GROUP_CONCAT(`dec` ORDER BY `dec` ASC SEPARATOR 0x01) AS `codepoints`
                FROM `$this->dbName`.`$this->tableName` 
                GROUP BY `mychar`;"
        );

        $warnings = $db->query('show warnings;');
        if ($warnings) {
            foreach ($warnings as $warning) {
                var_dump($warning);
                echo "exiting\n";

                exit(1);
            }
        }

        $charsetTable = [];
        // For each grouped set, write to stdout each column x and y as two space-
        // separated lists with a tab in between
        foreach ($rows as $row) {
            $charsetTable[] = [
                explode(chr(0x01), $row['characters']),
                array_map(function ($value) {
                    return (int)$value;
                }, explode(chr(0x01), $row['codepoints'])),
            ];
        }

        return $charsetTable;
    }

    /**
     * Debug array. Write compact format of array to stdout
     */
    protected static function dbga(array $array, $depth = 1): string
    {
        $values = [];
        foreach ($array as $value) {
            $values[] = !is_array($value) ? (string) $value : static::dbga($value, $depth + 1);
        }
        $string = '[' . implode(', ', $values) . ']';
        if ($depth > 1) {
            return $string;
        }
        echo $string . "\n\n";

        return '';
    }

    /**
     * Get blended array of strays, stray range, and folds from the internal charset table.
     * @return array of integer codepoints organized into elements
     *  - stray                 stray character
     *  - [from, to]            stray range
     *  - [foldTo, [fold, ...]] folding rule (map each fold to foldTo)
     */
    protected function getBlendedRules()
    {
        list($strays, $folds) = $this->getStraysAndFolds();

        $strays = $this->arrayRuns($strays);

        if (is_int($this->maxFoldRun) && $this->maxFoldRun > 2) {
            foreach ($folds as $foldTo => $folded) {
                list($newFold, $newStrays) = $this->arrayRuns($folded, $this->maxFoldRun, false);

                if (empty($newFold)) {
                    unset($folds[$foldTo]);
                } else {
                    $folds[$foldTo] = $newFold;
                }

                if (isset($newStrays[0][0]) && $newStrays[0][0] === $foldTo + 1) {
                    $pos = array_search($foldTo, $strays, true);
                    if ($pos !== false) {
                        $strays[$pos] = [$foldTo, $newStrays[0][1]];
                        array_shift($newStrays);
                    }
                }

                foreach ($newStrays as $newStray) {
                    $strays = array_merge($strays, [$newStray]);
                }
            }

            usort($strays, function ($a, $b) {
                return ($a[0] ?? $a) <=> ($b[0] ?? $b);
            });
        }

        return $this->blendCharsetRules($strays, $folds);
    }

    /**
     * Get separate arrays of strays and folds from the internal charset table.
     * @return array First element is array of stray integer codepoints. Second is array
     * of folding rules, i.e.
     *
     *      [
     *          [stray, ...],
     *          [
     *              foldTo => [fold, ...],
     *              ...
     *          ]
     *      ]
     */
    protected function getStraysAndFolds(): array
    {
        $strays = [];
        $folds = [];

        foreach ($this->getCharsetTable() as list(, $codepoints)) {
            $strays[] = $codepoints[0];
            if (count($codepoints) > 1) {
                $folds[array_shift($codepoints)] = $codepoints;
            }
        }

        return [$strays, $folds];
    }

    /**
     * Replace runs of $minRun or more integers in the given array into [from, to] doubles
     * representing the runs' ranges.
     * @param array $array The array of integers to process, e.g. [1,2,3,5,6,7,9]
     * @param int $minRun Detect runs no less than this long
     * @param bool $merged Return the input data merged in one array instead of two separate arrays.
     * @return array Either
     *  - a merged array of runs and strays, e.g. [[1,3], [5,7], 9], or
     *  - two arrays, strays in the first and runs in the second e.g. [[9], [[1,3], [5,7]]]
     */
    protected function arrayRuns(array $array, int $minRun = 3, bool $merged = true): array
    {
        $array = array_values($array);
        sort($array);
        $n = count($array);

        $runs = $merged ? [[]] : [[], []];
        for ($i = 0; $i < $n; $i += 1) {
            for ($j = 0; $i + $j < $n && $array[$i + $j] === $array[$i] + $j; $j += 1) {
            };
            if ($j >= $minRun) {
                $runs[$merged ? 0 : 1][] = [$array[$i], $array[$i + $j - 1]];
                $i += $j - 1;
            } else {
                $runs[0][] = $array[$i];
            }
        }

        return $merged ? $runs[0] : $runs;
    }

    /**
     * Blend into one array the given ranged stray codepoints and folding rules.
     * @param $rangedCodepoints array of codepoints and [from, to] ranges
     * @param $folds array of folds as [foldTo => [fold, ...], ...]
     * @return array Same format as @see getBlendedRules()
     */
    protected function blendCharsetRules(array $rangedCodepoints, array $folds): array
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

    /**
     * Encode a codepoint in Sphinx charset_table format.
     * @param int $codepoint
     * @return string
     */
    protected function sphinxCp(int $codepoint)
    {
        return $codepoint > 32 && $codepoint < 127 ? chr($codepoint) : sprintf('U+%02X', $codepoint);
    }

    /**
     * Format a folding rule for reading by a human.
     */
    protected function displayFolds(
        array $folds,
        \Closure $chr,
        string $foldSeparator = ' ',
        string $ruleSeparator = ' ← '
    ): string {
        return $chr($folds[0]) . $ruleSeparator
            . implode($foldSeparator, array_map(function ($fold) use ($folds, $chr) {
                return $chr($fold);
            }, $folds[1]));
    }

    /**
     * Format a bended charset table for reading by a human.
     */
    protected function displayTable(
        array $blendedTable,
        \Closure $chr,
        string $indentFolds = "     ",
        string $rangeSeparator = ' … '
    ): array {
        $outputLines = [];
        foreach ($blendedTable as $item) {
            $outputLines[] = is_int($item)
                ? $chr($item)
                : (is_array($item[1])
                    ? $indentFolds . $this->displayFolds($item, $chr)
                    : $chr($item[0]) . $rangeSeparator . $chr($item[1]));
        }

        return $outputLines;
    }

    /**
     * Format a folding rule as a string of maps in Sphinx charset_table format: CP1->CP2, CP1->CP2, ...
     */
    protected function sphinxFolds(array $folds, string $separator = ', '): string
    {
        return implode($separator, array_map(function ($fold) use ($folds) {
            return $this->sphinxCp($fold) . '->' . $this->sphinxCp($folds[0]);
        }, $folds[1]));
    }

    /**
     * Format a [from, to] integer codepoint range in Sphinx charset_table format: CP1..CP2
     */
    protected function sphinxStrayRange(array $range): string
    {
        return $this->sphinxCp($range[0]) . '..' . $this->sphinxCp($range[1]);
    }

    /**
     * Format a single stray codepoint in Sphinx charset_table format.
     */
    protected function sphinxStray(int $codepoint): string
    {
        return $this->sphinxCp($codepoint);
    }

    /**
     * Format a blended charset table in Sphinx charset_table format but structured with
     * whitespace to make it more human-readable.
     */
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

    /**
     * Format a blended charset table in Sphinx charset_table format compressed into fewer lines.
     */
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
                        $rules[] = $this->sphinxCp($fold) . '->' . $this->sphinxCp($item[0]);
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

    /**
     * Get the internal charset table in the human-editable format.
     * @param bool $hex Set true for codepoints in hex, false for decimal
     * @param bool $hex Set true for a table sorted by codepoint
     * @return string
     */
    public function getEditable($hex = true, $sort = true): string
    {
        $collatedCodepoints = $this->getCharsetTable();
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
        }

        return implode("\n", $lines);
    }

    /**
     * Get the processed charset table in UTF-8 charaters
     */
    public function getUtf8(): string
    {
        return implode("\n", $this->displayTable($this->getBlendedRules(), function ($codepoint) {
            return \IntlChar::chr($codepoint);
        })) . "\n";
    }

    /**
     * Get the processed charset table in hex codepoints
     */
    public function getHex(): string
    {
        return implode("\n", $this->displayTable($this->getBlendedRules(), function ($codepoint) {
            return sprintf('%02X', $codepoint);
        })) . "\n";
    }

    /**
     * Get the processed charset table in Sphinx charset_table format but structured with
     * whitespace to make it more human-readable.
     */
    public function getSphinx(): string
    {
        return implode(",\n", $this->readableSphinxTable($this->getBlendedRules())) . "\n";
    }

    /**
     * Get the processed charset table in Sphinx charset_table format compressed into fewer lines.
     */
    public function getCompressed(): string
    {
        return implode("\n", $this->compressSphinxTable($this->getBlendedRules())) . "\n";
    }
}
