<?php

declare(strict_types=1);

namespace yii\debug\panels\db;

use UIAwesome\Html\Helper\Encode;

use function preg_match_all;
use function strlen;
use function substr;

/**
 * Highlights SQL statements as escape-safe HTML for the DB panel and the EXPLAIN view.
 *
 * Tokenizes comments, string literals, quoted identifiers, bind parameters, numbers, and common SQL keywords in a
 * single pass, wrapping each token in a `yii-debug-sql-*` classed `<span>`. Every emitted byte — token or not — goes
 * through {@see Encode::content()}, so the returned markup is safe to inject with `->html()`.
 *
 * Usage example:
 * ```php
 * \UIAwesome\Html\Flow\Div::tag()
 *     ->class('yii-debug-db-sql')
 *     ->html(\yii\debug\panels\db\SqlHighlighter::highlight($row->query));
 * ```
 */
final class SqlHighlighter
{
    /**
     * Single-pass token pattern; alternation order gives comments and literals precedence over keywords.
     */
    private const string PATTERN = '~(?<comment>/\*.*?\*/|--[^\r\n]*)'
        . '|(?<str>\'(?:[^\'\\\\]|\\\\.|\'\')*\')'
        . '|(?<ident>"(?:[^"]|"")*"|`[^`]*`)'
        . '|(?<param>(?<!:):\w+|\?)'
        . '|(?<num>\b\d+(?:\.\d+)?\b)'
        . '|(?<kw>\b(?:SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|LEFT|RIGHT|INNER|OUTER|FULL|CROSS|NATURAL|ON'
        . '|USING|AS|AND|OR|NOT|IN|IS|NULL|TRUE|FALSE|LIKE|ILIKE|BETWEEN|EXISTS|CASE|WHEN|THEN|ELSE|END|GROUP|BY'
        . '|ORDER|HAVING|LIMIT|OFFSET|UNION|ALL|DISTINCT|INTO|VALUES|SET|CREATE|ALTER|DROP|TABLE|INDEX|VIEW|TRIGGER'
        . '|SEQUENCE|PRIMARY|FOREIGN|KEY|REFERENCES|CONSTRAINT|DEFAULT|CHECK|UNIQUE|ASC|DESC|WITH|RECURSIVE'
        . '|RETURNING|CAST|COALESCE|NULLIF|BEGIN|COMMIT|ROLLBACK|TRANSACTION|EXPLAIN|ANALYZE|SHOW|DESCRIBE)\b)~is';

    /**
     * Maps a matched named group to its `<span>` class; an empty class emits the escaped token unwrapped.
     */
    private const array TOKEN_CLASSES = [
        'comment' => 'yii-debug-sql-comment',
        'str' => 'yii-debug-sql-str',
        'ident' => '',
        'param' => 'yii-debug-sql-param',
        'num' => 'yii-debug-sql-num',
        'kw' => 'yii-debug-sql-kw',
    ];

    /**
     * Returns the SQL statement as fully escaped HTML with `yii-debug-sql-*` token spans.
     *
     * @param string $sql Raw SQL statement to highlight.
     */
    public static function highlight(string $sql): string
    {
        preg_match_all(self::PATTERN, $sql, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $html = '';
        $offset = 0;

        foreach ($matches as $match) {
            $position = $match[0][1];

            $html .= Encode::content(substr($sql, $offset, $position - $offset));
            $text = Encode::content($match[0][0]);

            $token = $text;

            foreach (self::TOKEN_CLASSES as $group => $class) {
                if (isset($match[$group]) && $match[$group][1] !== -1) {
                    $token = $class === '' ? $text : "<span class=\"{$class}\">{$text}</span>";
                }
            }

            $html .= $token;

            $offset = $position + strlen($match[0][0]);
        }

        return $html . Encode::content(substr($sql, $offset));
    }
}
