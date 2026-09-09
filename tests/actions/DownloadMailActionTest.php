<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\actions\DownloadMailAction;
use yii\debug\actions\ViewAction;
use yii\debug\collectors\MailCollector;
use yii\debug\exception\Message;
use yii\debug\tests\support\ActionTestCase;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use function file_put_contents;
use function mkdir;

/**
 * Unit tests for {@see DownloadMailAction} covering existing-file download responses, missing collector/file rejection,
 * and rejection of filenames containing a slash.
 */
#[Group('actions')]
final class DownloadMailActionTest extends ActionTestCase
{
    public function testActionDownloadMailStreamsExistingMailFile(): void
    {
        $module = $this->bootDebugModule();

        $mailCollector = $module
            ->getCollectorCoordinator()
            ->collector('mail');

        self::assertInstanceOf(
            MailCollector::class,
            $mailCollector,
            'Mail collector must be wired.',
        );

        $mailDir = Yii::getAlias($mailCollector->mailPath);

        @mkdir($mailDir, 0o777, true);

        $file = 'sample.eml';

        file_put_contents("{$mailDir}/{$file}", 'From: a@b');

        $response = $this->runDebugAction(
            new DownloadMailAction('download-mail'),
            $module,
            ['file' => $file],
        );

        self::assertInstanceOf(
            Response::class,
            $response,
            "Download must return a 'Response'.",
        );
    }

    public function testThrowNotFoundHttpExceptionWhenMailCollectorIsMissing(): void
    {
        $module = $this->bootDebugModule(collectorless: true);

        $action = new ViewAction('view');

        $action->setModule($module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::MAIL_COLLECTOR_NOT_FOUND->getMessage(),
        );

        $this->runDebugAction(new DownloadMailAction('download-mail'), $module, ['file' => 'sample.eml']);
    }

    public function testThrowNotFoundHttpExceptionWhenMailFileDoesNotExist(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::MAIL_FILE_NOT_FOUND->getMessage(),
        );

        $this->runDebugAction(
            new DownloadMailAction('download-mail'),
            $module,
            ['file' => 'missing-file.eml'],
        );
    }

    public function testThrowNotFoundHttpExceptionWhenMailFileNameContainsSlash(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::MAIL_FILE_NOT_FOUND->getMessage(),
        );

        $this->runDebugAction(
            new DownloadMailAction('download-mail'),
            $module,
            ['file' => 'subdir/sample.eml'],
        );
    }
}
