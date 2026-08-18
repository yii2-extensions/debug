<?php

declare(strict_types=1);

namespace yii\debug\actions;

use Yii;
use yii\web\{NotFoundHttpException, Response};

use function basename;
use function is_file;
use function strpos;

/**
 * Streams a captured mail file as a download.
 */
class DownloadMailAction extends Action
{
    /**
     * Runs the action.
     *
     * @param string $file Name of the captured `.eml` file to stream.
     *
     * @throws NotFoundHttpException When the file name contains a path separator or the file does not exist on disk.
     *
     * @return Response Response that emits the mail file as an attachment.
     */
    public function run(string $file): Response
    {
        $filePath = Yii::getAlias($this->getMailCollector()->mailPath) . '/' . basename($file);

        if (
            strpos($file, '\\') !== false
            || strpos($file, '/') !== false
            || !is_file($filePath)
        ) {
            throw new NotFoundHttpException(
                'Mail file not found',
            );
        }

        return Yii::$app->response->sendFile($filePath);
    }
}
