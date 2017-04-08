<?php

namespace spinitron\c2ct;

class CollationToCharsetTable
{
    /**
     * @var string Name of the working database to use for processing.
     */
    public $dbname = 'collation_2_charset_table';
    /**
     * @var string Name of the working table to use for processing.
     *
     *   *** WARNING *** The script drops and creates this table on each run!
     */
    public $tablename = 'collation_2_charset_table';
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
     * Codepoint ranges to include in a Sphinx charet_table.
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
     * Characters that you want to function as keyword separators must be exluced
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

    public function getEditableTable(): string
    {
        $db = new \PDO($this->pdo_dsn, $this->pdo_user, $this->pdo_pass);
        $db->query("SET NAMES '$this->charset' COLLATE '$this->collation';");
        $db->query("CREATE DATABASE IF NOT EXISTS `$this->dbname`;");
        $db->query("DROP TABLE IF EXISTS `$this->dbname`.`$this->tablename`;");
        $db->query(
            "CREATE TABLE IF NOT EXISTS `$this->dbname`.`$this->tablename` (
                `dec` int(11) NOT NULL,
                `mychar` char(1) CHARACTER SET $this->charset COLLATE $this->collation NOT NULL,
                `hex` varchar(8) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                PRIMARY KEY  (`dec`)
            ) ENGINE=MyISAM;"
        );

        $statement = $db->prepare(
            "INSERT IGNORE INTO `$this->dbname`.`$this->tablename` (`dec`, `mychar`, `hex`) VALUES (:dec, :chr, :hex)"
        );

        /** @var array $ranges */
        $this->iterateRanges(true, function ($codepoint) use ($statement) {
            $statement->execute([
                ':dec' => $codepoint,
                ':chr' => mb_convert_encoding(hex2bin(sprintf('%08X', $codepoint)), 'UTF-8', 'UTF-32BE'),
                ':hex' => sprintf('%X', $codepoint),
            ]);
        });

        // Now the interesting bit. Use mysql's GROUP BY to group rows of characters
        // according to the collation. Use GROUP_CONCAT to get each set of chars the
        // collation considers equivalent as:
        //	  x: a comma separated list of UTF-8 characters
        //	  y: a comma separated list of hex unicode codepoints
        $rows = $db->query(
            "SELECT GROUP_CONCAT(`mychar` ORDER BY `dec` ASC SEPARATOR ',') AS x,
            GROUP_CONCAT(`hex` ORDER BY `dec` ASC SEPARATOR ',') AS y
            FROM `$this->dbname`.`$this->tablename` GROUP BY `mychar`;"
        );

        $table = '';
        // For each grouped set, write to stdout each column x and y as two comma-
        // separated lists with a tab in between
        if ($rows) {
            foreach ($rows as $row) {
                $table .= $row['x'] . "\t" . $row['y'] . "\n";
            }
        }

        return $table;
    }

    protected function iterateRanges(bool $folding, \Closure $function)
    {
        $ranges = array_keys(array_filter($this->ranges, function ($fold) use ($folding) {
            return $fold === $folding;
        }));

        foreach ($ranges as $range) {
            if (!preg_match('/^([0-9A-F]{1,8})-([0-9A-F]{1,8})$/i', $range, $matches)) {
                die("Bad range spec: '$range'\n");
            }

            for ($codepoint = hexdec($matches[1]); $codepoint <= hexdec($matches[2]); $codepoint += 1) {
                if (in_array(\IntlChar::charType($codepoint), $this->exclude_character_categories)) {
                    continue;
                }

                foreach ($this->exclude_properties as $property) {
                    if (\IntlChar::hasBinaryProperty($codepoint, $property)) {
                        continue;
                    }
                }

                $function($codepoint);
            }
        }
    }

    public function getCharsetTable(string $editableTable): string
    {
        /**
         * @var string The first line of output
         */
        $startswith = "    charset_table = ";
        /**
         * @var int Limit num. chars per output line
         */
        $linewidth = 120;
        /**
         * @var int spaces to indent continuation lines
         */
        $indent = 8;
        /**
         * @var int Min. num. hex digits per codepoint literal
         */
        $padto = 2;
        /**
         * @var string printf format to convert int to charset_table-format codepoint literal
         */
        $format = 'U+%0' . $padto . 'x';

        /** @var array[] Sets of codepoints collated with equal value, to be folded by Sphinx */
        $sets = [];
        /** @var int[] Terminal codepoints Sphinx will actually index */
        $singles = [];

        // parse each input line
        foreach (explode("\n", $editableTable) as $line) {
            // search each input line for a tab character
            if (preg_match('/^(.+)\t(.+)$/u', $line, $matches)) {
                // if the part after the tab on the unput line is ...
                if (preg_match('/^[0-9a-f]{1,8}$/i', $matches[2])) {
                    // ... a single hex codepoint then it's a singleton,
                    // add it to the list of singles
                    $singles[] = hexdec($matches[2]);
                } elseif (preg_match('/^[0-9a-f]{1,8}(,[0-9a-f]{1,8})+$/i', $matches[2])) {
                    // ... a comma separatred list of codepoints,
                    // it's a set of chars to be folded to the frst of them,
                    // split it and add to the list of sets
                    $sets[] = array_map('hexdec', explode(',', $matches[2]));
                }
            }
        }

        // add the folding targets to singles
        foreach ($sets as $codes) {
            $singles[] = $codes[0];
        }

        // add included but not folded ranges to singles, respecting Unicode category and property exclusions
        $this->iterateRanges(false, function ($codepoint) use (&$singles) {
            $singles[] = $codepoint;
        });

        // encode the rules for sphinx.
        // do the singles (folding targets) first
        sort($singles);

        // run detection state machine var
        $run = false;

        // elements of the output charset_table
        $elements = [];
        $element = sprintf($format, $singles[0]);
        for ($i = 1; $i < count($singles) - 1; $i++) {
            // detect runs of consecutive codeponts and use
            // Sphinx's .. notation, e.g.: 'U+041..U+05A'
            if ($run) {
                if ($singles[$i] != $singles[$i + 1] - 1) {
                    $element .= ".." . sprintf($format, $singles[$i]);
                    $run = false;
                }
            } else {
                $run = $singles[$i] == $singles[$i - 1] + 1
                    && $singles[$i] == $singles[$i + 1] - 1;
                if (!$run) {
                    $elements[] = $element;
                    $element = sprintf($format, $singles[$i]);
                }
            }
        }

        // finish up after the end of the above loop
        if ($run) {
            $element .= ".." . sprintf($format, $singles[$i]);
            $elements[] = $element;
        } else {
            $elements[] = sprintf($format, $singles[$i]);
        }

        // encode the folding rules into elements
        foreach ($sets as $codes) {
            $to = sprintf($format, $codes[0]);
            for ($i = 1; $i < count($codes); ++$i) {
                $elements[] = sprintf($format, $codes[$i]) . "->$to";
            }
        }

        // format output for the sphinx config file
        $charsetTable = $startswith;
        $lineLength = strlen($startswith);
        $last = array_pop($elements);
        foreach ($elements as $element) {
            $element .= ', ';
            if ($lineLength + strlen($element) >= $linewidth) {
                $charsetTable .= "\\\n" . str_repeat(' ', $indent);
                $lineLength = $indent;
            }
            $charsetTable .= $element;
            $lineLength += strlen($element);
        }
        $charsetTable .= "$last\n";

        return $charsetTable;
    }
}
