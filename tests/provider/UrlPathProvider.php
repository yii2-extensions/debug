<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Characterization cases for captured URL display without changing the original diagnostic value.
 */
final class UrlPathProvider
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'absolute URL' => [
            'https://example.test:8443/orders?sort=id#row',
            '/orders?sort=id#row',
        ];
        yield 'authority only' => [
            'https://example.test',
            '/',
        ];
        yield 'console invocation' => [
            'php yii migrate/up',
            'php yii migrate/up',
        ];
        yield 'credentials and IPv6' => [
            'https://user:pass@[::1]:8080/a',
            '/a',
        ];
        yield 'empty fragment' => [
            'https://example.test/a#',
            '/a',
        ];
        yield 'empty input' => [
            '',
            '',
        ];
        yield 'empty query and fragment' => [
            'https://example.test?#',
            '/',
        ];
        yield 'empty query' => [
            'https://example.test/a?',
            '/a',
        ];
        yield 'encoded components' => [
            'https://example.test/a%2Fb?q=%23%26#x%20y',
            '/a%2Fb?q=%23%26#x%20y',
        ];
        yield 'fragment without path' => [
            '#details',
            '/#details',
        ];
        yield 'invalid network path' => [
            '///broken?x=1#b',
            '///broken?x=1#b',
        ];
        yield 'invalid port' => [
            'http://example.test:99999/a?q=1#b',
            'http://example.test:99999/a?q=1#b',
        ];
        yield 'markup remains diagnostic text' => [
            'https://example.test/<b>?q="a"&x=1#<i>',
            '/<b>?q="a"&x=1#<i>',
        ];
        yield 'missing host' => [
            'http://',
            'http://',
        ];
        yield 'network path' => [
            '//example.test/a?x=1#b',
            '/a?x=1#b',
        ];
        yield 'nonhierarchical scheme' => [
            'mailto:user@example.test?subject=Hi#part',
            'user@example.test?subject=Hi#part',
        ];
        yield 'query without path' => [
            '?x=1',
            '/?x=1',
        ];
        yield 'relative path' => [
            'orders/42?x=1#details',
            'orders/42?x=1#details',
        ];
        yield 'root path' => [
            '/',
            '/',
        ];
        yield 'zero fragment' => [
            'https://example.test#0',
            '/#0',
        ];
        yield 'zero path' => [
            '0',
            '0',
        ];
        yield 'zero query' => [
            'https://example.test?0',
            '/?0',
        ];
    }
}
