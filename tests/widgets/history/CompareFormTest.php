<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\history;

use PHPForge\Debug\Storage\RequestSummary;
use yii\debug\tests\support\TestCase;
use yii\web\View;

use function dirname;

/**
 * Tests unique, escaped capture labels in the Yii2 comparison form.
 */
final class CompareFormTest extends TestCase
{
    public function testSameSecondCapturesKeepDistinctLabels(): void
    {
        $this->mockWebApplication();
        $first = RequestSummary::create('6a9ec2295ddcd414251546')->withRequest('/<script>', 'GET', '', 1000.1);
        $second = RequestSummary::create('6a9ec22932a3c954772352')->withRequest('/<script>', 'GET', '', 1000.2);

        $html = (new View())->renderFile(
            dirname(__DIR__, 3) . '/src/views/default/_compare-form.php',
            [
                'manifest' => [$first->tag => $first, $second->tag => $second],
                'baseline' => $first->tag,
                'target' => $second->tag,
            ],
        );

        self::assertStringContainsString(
            "/&lt;script&gt; · {$first->tag}",
            $html,
            'The baseline option must retain its full unique tag and escape diagnostic text.',
        );
        self::assertStringContainsString(
            "/&lt;script&gt; · {$second->tag}",
            $html,
            'Same-second captures must remain distinguishable in the selection form.',
        );
    }
}
