Create a custom Sphinx charset_table from a MySQL collation
=====

[Collation-to-Charset-Table](https://github.com/tom--/Collation-to-Charset-Table) can

- extract a charset table from a MySQL collation

- exclude characters based on Unicode property/category

- write/read a human-editable charset table file for your customization

- human-readable displays of your custom charset table

- encode your custom charset table into Sphinx `charset_table` config

- encode U+20 to U+4FF of utf8mb4_unicode_ci into 11 KiB of `charset_table`

- or U+20 to U+1FFFF (82,765 codepoints) into 70 KiB

Requires PHP 7, ICU, Intl extension, and a MySQL-like DB server. Take care
that your Unicode versions in both PHP and MySQL are up-to-date.


Quick start: Using the `c2ct` command
----


In *config.php* adjust these lines to your environment

```php
    'pdoDsn' => 'mysql:host=127.0.0.1',
    'pdoUser' => 'root',
    'pdoPass' => require __DIR__ . '/pdo_password.php',
```

Check the console command works by displaying the help text. In the repo's directory run

    ./c2ct

Create a DB charset table and load into it characters from configured ranges

    ./c2ct newtable

Collate the characters in the DB and display as a "charset table"

    ./c2ct utf8

Display the same charset table but using hex codepoints

    ./c2ct hex

Display the same charset table in the human-editable format (see below)

    ./c2ct editable

The same human-editable format but sorted by codepoint rather than collation

    ./c2ct editable -c

Display the charset table in Sphinx's `charset_table` configuration format

    ./c2ct sphinx

Display the same Sphinx `charset_table` compressed onto fewer lines

    ./c2ct compressed

Write a charset table to a file for manual editing (to customize it for your app)

    ./c2ct editable -c > my_charset_table.txt

Edit your charset table file and use it as input to any of the four display commands:

    ./c2ct utf8 - < my_charset_table.txt
    ./c2ct hex - < my_charset_table.txt
    ./c2ct sphinx - < my_charset_table.txt
    ./c2ct compressed - < my_charset_table.txt

Save your customized charset table for use in a Sphinx config file

    ./c2ct compressed - < my_charset_table.txt > my_charset_table.conf

But you shouldn't use that *charset_table.conf* until you understand what it represents,
There's no such thing as a standard charset table. You need a charset table
that's appropriate for *your* application. Customize it—don't criticize it.

Example output
----

Examples of all five output files for U+0000..U+024F using the default config

- [example_editable.txt](example_editable.txt)
- [example_utf8.txt](example_utf8.txt)
- [example_hex.txt](example_hex.txt)
- [example_sphinx.conf](example_sphinx.conf)
- [example_compressed.conf](example_compressed.conf)

Matching Sphinx `charset_table` to a MySQL collation
----

If your app is localized to English then perhaps you can live with

    charset_table = 0..9, english

But my app deals with music metadata, e.g. artist names, song names, etc.
and I use MySQL to store the strings and Sphinx for fast search. The strings
can be from anywhere in the world and made-up names, e.g. for a song, can include
weird characters.

The app needs to work for users accustomed to any locale so I use one of
MySQL's *_general_ci or *_unicode_ci collations so

    SELECT * FROM `artist` WHERE `name` LIKE "Motor%";

can find "Motörhead". I want Sphinx to be able to find the same records so it
needs to match characters with roughly the same rules as the MySQL collation.

If a user enters the letter *D* I want Sphinx to match it to any of the characters
that MySQL would match it to, i.e.

    D d Ď ď ͩ ᴰ ᵈ Ḋ ḋ Ḍ ḍ Ḏ ḏ Ḑ ḑ Ḓ ḓ

Automation
-----

Unicode has expanded over the years. We now use utf8mb4 encoding and collate with
utf8mb4_unicode_ci, which is all pretty sophisticated. I don't have the patience
or understanding to author a `charset_table` that covers even a fraction of
Unicode. But the utf8mb4_unicode_ci encapsulates much of the understanding I need.

The core idea is to "dump" the collation as a set of folding rules and convert
them to Sphinx `charset_table` format. Put all the characters you care about in a
`CHAR(1)` column with that collation and then

```sql
SELECT GROUP_CONCAT(mychar) FROM mytable GROUP BY mychar;
```

Processing
----

The `c2ct newtable` command creates a MySQL table and populates it with
the codepoint ranges you configure excluding

- undefined codepoints, control characters, and SPACE
- characters with Unicode general categories you configure
- characters with Unicode character properties you configure

The four output commands `hex`, `utf8`, `sphinx`, and `compressed` process
and write out a charset table in their respective formats based on the data in the
DB table or based on an input text file in the human-editable format
described below. The `editable` command outputs the data from the DB table in
the human-editable format.


Editable file format
----

Each line (terminated by newline) represents a set of characters that collate together.
Each line has two parts separated by a tab character. Before the tab is a (space-separated)
list of utf8 characters and after the tab is the corresponding (space-separated) list of hex
codepoints.

For example, the Basic Latin and Latin-1 Supplement (with my exclusions) in human-editable
format sorted by codepoint

    $	24
    +	2b
    0	30
    1 ¹	31 b9
    2 ²	32 b2
    3 ³	33 b3
    4	34
    5	35
    6	36
    7	37
    8	38
    9	39
    <	3c
    =	3d
    >	3e
    A a ª À Á Â Ã Ä Å à á â ã ä å	41 61 aa c0 c1 c2 c3 c4 c5 e0 e1 e2 e3 e4 e5
    B b	42 62
    C c Ç ç	43 63 c7 e7
    D d	44 64
    E e È É Ê Ë è é ê ë	45 65 c8 c9 ca cb e8 e9 ea eb
    F f	46 66
    G g	47 67
    H h	48 68
    I i Ì Í Î Ï ì í î ï	49 69 cc cd ce cf ec ed ee ef
    J j	4a 6a
    K k	4b 6b
    L l	4c 6c
    M m	4d 6d
    N n Ñ ñ	4e 6e d1 f1
    O o º Ò Ó Ô Õ Ö ò ó ô õ ö	4f 6f ba d2 d3 d4 d5 d6 f2 f3 f4 f5 f6
    P p	50 70
    Q q	51 71
    R r	52 72
    S s	53 73
    T t	54 74
    U u Ù Ú Û Ü ù ú û ü	55 75 d9 da db dc f9 fa fb fc
    V v	56 76
    W w	57 77
    X x	58 78
    Y y Ý ý ÿ	59 79 dd fd ff
    Z z	5a 7a
    ^	5e
    `	60
    |	7c
    ~	7e
    ¢	a2
    £	a3
    ¤	a4
    ¥	a5
    ¦	a6
    ¨	a8
    ©	a9
    ¬	ac
    ®	ae
    ¯	af
    °	b0
    ±	b1
    ´	b4
    µ	b5
    ¸	b8
    ¼	bc
    ½	bd
    ¾	be
    Æ æ	c6 e6
    Ð ð	d0 f0
    ×	d7
    Ø ø	d8 f8
    Þ þ	de fe
    ß	df
    ÷	f7

When `c2ct` reads such a file, it declares the first codepoint in each line's codepoint list
as [allowed](http://sphinxsearch.com/docs/current.html#conf-charset-table)
 and maps any subsequent codepoints in the list
to the first one. `c2ct` parses the hex codepoint list on each line,
not the UTF-8 characters, which are there for your reference while editing.

For example, `c2ct` encodes the line

    Y y Ý ý ÿ	59 79 dd fd ff

into these Sphinx `charset_table` rules

    Y, y->Y, U+DD->Y, U+FD->Y, U+FF->Y

So Sphinx will encode `Y` in indexed documents as a search keyword character and will map the
other four (`y Ý ý ÿ`) to `Y`

Note: `c2ct` does run encoding so you probably won't find `Y` in your output. Something like
this, in which `Y` is part of the `A..Z` range, is more typical of output from `c2ct sphinx`.

    $,
    +,
    0..9,
       U+B9->1,
       U+B2->2,
       U+B3->3,
    <..>,
    A..Z,
       a->A,U+AA->A,U+C0->A,U+C1->A,U+C2->A,U+C3->A,U+C4->A,U+C5->A,U+E0->A,U+E1->A,U+E2->A,U+E3->A,U+E4->A,U+E5->A,
       b->B,
       c->C,U+C7->C,U+E7->C,
    ...

### Editing tricks

 `./c2ct` scans the part of each line after the tab for a valid space-separated codepoint list and
ignores everything else. So you may 1) keep but invalidate a codepoint (list), 2) add comments, for
example this editable charset table

    Z z	5a 7a
    ^	-5e             # behaves as separator
    ' ` ’	27 60 2019      # fold these to apostrophe
    |	-7c             # behaves as separator
    Þ þ	-de -fe         # confuses users
    ¢	a2

writes the following in which the 'No codepoints found' are to stderr

    No codepoints found on line 2
    No codepoints found on line 4
    No codepoints found on line 5
    ',
       `->', U+2019->',
    Z,
       z->Z,
    U+A2

## Customize

Among other things you can

- customize the input [Unicode ranges](https://github.com/tom--/Collation-to-Charset-Table/blob/master/CollationToCharsetTable.php#L54-L75)
- customize the exclusions based on character [categories] and properties](https://github.com/tom--/Collation-to-Charset-Table/blob/master/CollationToCharsetTable.php#L96-L106)
- fine-tune the collation-based charset-table by manual editing

Fine tuning the human-readable file requires understanding of your app, how you expect
people to use it, and what outputs they might expect relative to their inputs.

For example, I want to put back some of the punctuation lost to the exclusions,
APOSTROPHE, for example, which is important in names like O'Brien. So I put that back
as a terminal. Sometimes the same
name appears as O’Brien (with RIGHT SINGLE QUOTATION MARK instead) so I add that to the
same line as APOSTROPHE thus

    ' ’     27 2019


Normal Form C
----

The `*_unicode_ci` collations are much more sophisticated than `*_general_ci`. So,
given that Sphinx only folds individual codepoints, not graphemes or whatnot, it's more
important than ever to normalize to Normal Form C before inserting strings to
the DB and before using strings in Sphinx queries. In PHP you can use
[`\Normalizer::normalize()`](https://secure.php.net/manual/en/normalizer.normalize.php).


Feedback please
----

This problem (and doing internationalization right in general) has become more complex
since I [first started](https://thefsb.wordpress.com/2009/04/27/converting-phpmysqlapache-app-from-latin-1-to-utf-8/)
on this road. People are really using a lot of Unicode characters now. We have to work with
the Supplementary Multilingual Plane now, where emoticons and other fun stuff live.
Where utf8_general_ci was OK a decade ago, utf8mb4_unicode_ci is *de rigueur* now.

Collation-to-Charset-Table is more complex now to help me cope. I need the new features
to analyze and understand collations and to help automate the exclusions I need.

But I don't really know what I'm doing (I am no internationalization expert) and I could use
your help, if you have any to give.

If you have any comments or thoughts, please use the Github issue tracker. Even if you
just used the tool I'd like to hear about it:

- what your Sphinx indexes do in your app?
- how did you configure `CollationToCharsetTable`?
- why and in what ways did you edit the charset table?
- was it useful? If not, how so?

License
-----

Copyright (c) 2012-2017, Tom Worster <fsb@thefsb.org>

Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby granted, provided that the above copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
