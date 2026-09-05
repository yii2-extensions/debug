<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\tests\history\HistoryPresentationCompatibilityTest;

/**
 * Characterization data for {@see HistoryPresentationCompatibilityTest}.
 */
final class HistoryPresentationProvider
{
    /**
     * @return iterable<string, array{int, list<int>, bool, string, string, array<string, mixed>}>
     */
    public static function rowAttributes(): iterable
    {
        yield 'custom critical success' => [
            200,
            [200],
            false,
            '200',
            '',
            ['class' => 'yii-debug-row-danger'],
        ];
        yield 'default critical status' => [
            400,
            [400, 404, 500],
            true,
            '400',
            '1',
            ['class' => 'yii-debug-row-danger'],
        ];
        yield 'disabled critical statuses' => [
            500,
            [],
            true,
            '500',
            '1',
            [],
        ];
        yield 'unknown status' => [
            0,
            [400, 404, 500],
            false,
            '0',
            '',
            [],
        ];
        yield 'unlisted error status' => [
            418,
            [400, 404, 500],
            false,
            '418',
            '',
            [],
        ];
    }

    /**
     * @return iterable<string, array{string, string, bool, string, string, string}>
     */
    public static function textCells(): iterable
    {
        yield 'empty fields' => [
            '',
            '',
            false,
            '',
            '<span class="yii-debug-url-cell"></span>',
            'No',
        ];
        yield 'get request' => [
            'GET',
            '/page',
            true,
            '<span class="yii-debug-method yii-debug-verb-get">GET</span>',
            '<span class="yii-debug-url-cell" title="/page">/page</span>',
            'Yes',
        ];
        yield 'lowercase method is not rewritten' => [
            'head',
            '/head',
            false,
            '<span class="yii-debug-method yii-debug-verb-get">head</span>',
            '<span class="yii-debug-url-cell" title="/head">/head</span>',
            'No',
        ];
        yield 'post request' => [
            'POST',
            '/submit',
            false,
            '<span class="yii-debug-method yii-debug-verb-post">POST</span>',
            '<span class="yii-debug-url-cell" title="/submit">/submit</span>',
            'No',
        ];
        yield 'patch uses put vocabulary' => [
            'PATCH',
            '/edit',
            true,
            '<span class="yii-debug-method yii-debug-verb-put">PATCH</span>',
            '<span class="yii-debug-url-cell" title="/edit">/edit</span>',
            'Yes',
        ];
        yield 'delete request' => [
            'DELETE',
            '/remove',
            false,
            '<span class="yii-debug-method yii-debug-verb-delete">DELETE</span>',
            '<span class="yii-debug-url-cell" title="/remove">/remove</span>',
            'No',
        ];
        yield 'diagnostic text is escaped only for rendering' => [
            '<get>',
            '/search?q=<tag>&x="quote"',
            true,
            '<span class="yii-debug-method yii-debug-verb-other">&lt;get&gt;</span>',
            '<span class="yii-debug-url-cell" title="/search?q=&lt;tag&gt;&amp;x=&quot;quote&quot;">/search?q=&lt;tag&gt;&amp;x="quote"</span>',
            'Yes',
        ];
    }
}
