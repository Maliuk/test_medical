<?php

namespace app\jobs;

use app\services\InvoiceImportService;
use yii\base\BaseObject;
use yii\mongodb\Exception;
use yii\queue\JobInterface;

/**
 * Job for importing a chunk of medical data.
 */
class ImportChunkJob extends BaseObject implements JobInterface
{
    /**
     * @var array Data chunk to import.
     */
    public array $data;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function execute($queue): void
    {
        $service = new InvoiceImportService();
        $service->importChunk($this->data);
    }
}
