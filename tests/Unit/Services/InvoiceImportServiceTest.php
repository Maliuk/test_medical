<?php

namespace app\tests\Unit\Services;

use app\services\InvoiceImportService;
use app\models\InvoiceElastic;
use Yii;
use yii\mongodb\Connection;

class InvoiceImportServiceTest extends \Codeception\Test\Unit
{
    protected \app\tests\Support\UnitTester $tester;
    private InvoiceImportService $service;
    private string $csvPath;
    private $collection;
    private $oldMongodb;
    private $oldEs;

    protected function _before()
    {
        $this->service = new InvoiceImportService();
        $this->csvPath = Yii::getAlias('@app/tests/_data/import_test.csv');

        // Mock MongoDB
        $this->collection = $this->getMockBuilder(\yii\mongodb\Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert', 'createIndex'])
            ->getMock();
        
        $mongodb = $this->getMockBuilder(\yii\mongodb\Connection::class)
            ->onlyMethods(['getCollection'])
            ->getMock();
        $mongodb->method('getCollection')->willReturn($this->collection);
        
        $this->oldMongodb = Yii::$app->mongodb;
        Yii::$app->set('mongodb', $mongodb);

        // Mock ES
        $es = $this->getMockBuilder(\yii\elasticsearch\Connection::class)
            ->onlyMethods(['createCommand', 'createBulkCommand'])
            ->getMock();
        $bulk = $this->getMockBuilder(\yii\elasticsearch\BulkCommand::class)
            ->disableOriginalConstructor()
            ->getMock();
        $es->method('createBulkCommand')->willReturn($bulk);
        $es->method('createCommand')->willReturn($this->createMock(\yii\elasticsearch\Command::class));

        $this->oldEs = Yii::$app->elasticsearch;
        Yii::$app->set('elasticsearch', $es);
    }

    protected function _after()
    {
        Yii::$app->set('mongodb', $this->oldMongodb);
        Yii::$app->set('elasticsearch', $this->oldEs);
    }

    public function testImportCsv()
    {
        $this->collection->method('insert')->willReturn(true);
        $results = $this->service->importCsv($this->csvPath);

        verify($results['total'])->equals(2);
        verify($results['inserted'])->equals(2);
    }

    public function testImportCsvDuplicates()
    {
        // First row success, second row duplicate (throws exception)
        $this->collection->expects($this->exactly(2))
            ->method('insert')
            ->willReturnOnConsecutiveCalls(
                true,
                $this->throwException(new \yii\mongodb\Exception('duplicate key error'))
            );
        
        $results = $this->service->importCsv($this->csvPath);

        verify($results['total'])->equals(2);
        verify($results['inserted'])->equals(1);
        verify($results['duplicates'])->equals(1);
    }

    public function testImportCsvToQueue()
    {
        // Mock queue
        $queue = $this->getMockBuilder(\yii\queue\sync\Queue::class)
            ->onlyMethods(['push'])
            ->getMock();
        
        $queue->expects($this->exactly(1)) // 2 valid rows in 1 chunk (chunk size 100)
            ->method('push')
            ->willReturn('1');
        
        $oldQueue = Yii::$app->queue;
        Yii::$app->set('queue', $queue);

        try {
            $jobsCount = $this->service->importCsvToQueue($this->csvPath);
            verify($jobsCount)->equals(1);
        } finally {
            Yii::$app->set('queue', $oldQueue);
        }
    }
}
