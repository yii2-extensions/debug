<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\db\SqlHighlighter;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see SqlHighlighter} covering token classification, escaping, and pass-through behavior.
 */
#[Group('panel')]
#[Group('db')]
final class SqlHighlighterTest extends TestCase
{
    public function testHighlightEscapesHtmlInsideStringLiteralsAndPlainSegments(): void
    {
        self::assertSame(
            '<span class="yii-debug-sql-kw">SELECT</span> a &amp; b, '
            . "<span class=\"yii-debug-sql-str\">'&lt;script&gt;alert(1)&lt;/script&gt;'</span> "
            . '<span class="yii-debug-sql-kw">FROM</span> t',
            SqlHighlighter::highlight("SELECT a & b, '<script>alert(1)</script>' FROM t"),
            'Markup must be escaped in every segment.',
        );
    }

    public function testHighlightHandlesBackslashEscapedQuoteInsideStringLiteral(): void
    {
        self::assertSame(
            "<span class=\"yii-debug-sql-str\">'a\\'s'</span>",
            SqlHighlighter::highlight("'a\\'s'"),
            'Backslash escape must extend the literal.',
        );
    }

    public function testHighlightKeepsDigitsAttachedToIdentifiersUnwrapped(): void
    {
        self::assertSame(
            '<span class="yii-debug-sql-kw">FROM</span> tbl1',
            SqlHighlighter::highlight('FROM tbl1'),
            'Digits inside identifiers must stay plain.',
        );
    }

    public function testHighlightKeepsDoubleColonCastsUnwrapped(): void
    {
        self::assertSame(
            'x::text',
            SqlHighlighter::highlight('x::text'),
            'Cast operators must not read as parameters.',
        );
    }

    public function testHighlightKeepsKeywordsInsideStringLiteralsUnwrapped(): void
    {
        self::assertSame(
            "<span class=\"yii-debug-sql-str\">'it''s from where'</span>",
            SqlHighlighter::highlight("'it''s from where'"),
            'Doubled-quote escape must extend the literal.',
        );
    }

    public function testHighlightLeavesQuotedIdentifiersUnwrapped(): void
    {
        self::assertSame(
            '<span class="yii-debug-sql-kw">SELECT</span> "from", `where` '
            . '<span class="yii-debug-sql-kw">FROM</span> t',
            SqlHighlighter::highlight('SELECT "from", `where` FROM t'),
            'Quoted identifiers must stay span-free.',
        );
    }

    public function testHighlightMatchesKeywordsCaseInsensitively(): void
    {
        self::assertSame(
            '<span class="yii-debug-sql-kw">select</span> <span class="yii-debug-sql-num">1</span>',
            SqlHighlighter::highlight('select 1'),
            'Lowercase keywords must be wrapped.',
        );
    }

    public function testHighlightPreservesLeadingAndTrailingPlainSegments(): void
    {
        self::assertSame(
            '  (<span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>)  ',
            SqlHighlighter::highlight('  (SELECT 1)  '),
            'Gap and tail segments must be preserved.',
        );
    }

    public function testHighlightRespectsWordBoundariesAroundKeywords(): void
    {
        self::assertSame(
            'SELECTED_FROM_X',
            SqlHighlighter::highlight('SELECTED_FROM_X'),
            'Keyword lookalikes must stay plain.',
        );
    }

    public function testHighlightReturnsEmptyStringForEmptyInput(): void
    {
        self::assertSame(
            '',
            SqlHighlighter::highlight(''),
            'Empty input must yield an empty string.',
        );
    }

    public function testHighlightReturnsEscapedInputWhenNoTokenMatches(): void
    {
        self::assertSame(
            'foo &lt;bar&gt;',
            SqlHighlighter::highlight('foo <bar>'),
            'Tokenless input must be escaped verbatim.',
        );
    }

    public function testHighlightWrapsBindParametersAndPlaceholders(): void
    {
        self::assertSame(
            '<span class="yii-debug-sql-kw">WHERE</span> id <span class="yii-debug-sql-kw">IN</span> '
            . '(<span class="yii-debug-sql-param">:ids</span>, <span class="yii-debug-sql-param">?</span>)',
            SqlHighlighter::highlight('WHERE id IN (:ids, ?)'),
            'Named and positional parameters must be wrapped.',
        );
    }

    public function testHighlightWrapsIntegerAndDecimalNumbers(): void
    {
        self::assertSame(
            '<span class="yii-debug-sql-kw">LIMIT</span> <span class="yii-debug-sql-num">10</span> '
            . '<span class="yii-debug-sql-kw">OFFSET</span> <span class="yii-debug-sql-num">3.14</span>',
            SqlHighlighter::highlight('LIMIT 10 OFFSET 3.14'),
            'Integers and decimals must be wrapped.',
        );
    }

    public function testHighlightWrapsLineAndBlockComments(): void
    {
        self::assertSame(
            "<span class=\"yii-debug-sql-comment\">/* multi\nline */</span> "
            . '<span class="yii-debug-sql-num">1</span> '
            . '<span class="yii-debug-sql-comment">-- tail note</span>',
            SqlHighlighter::highlight("/* multi\nline */ 1 -- tail note"),
            'Both comment forms must be wrapped.',
        );
    }
}
