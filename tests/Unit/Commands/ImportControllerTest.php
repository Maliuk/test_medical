<?php

namespace app\tests\Unit\Commands;

use app\commands\ImportController;
use app\models\InvoiceElastic;
use Yii;
use yii\console\ExitCode;

class ImportControllerTest extends \Codeception\Test\Unit
{
    private BufferedImportController $controller;

    protected function _before()
    {
        $this->controller = new BufferedImportController('import', Yii::$app);
        
        // Mock ES
        $this->esCommand = $this->getMockBuilder(\yii\elasticsearch\Command::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search', 'indexExists', 'createIndex', 'deleteIndex'])
            ->getMock();
        $this->esCommand->method('indexExists')->willReturn(true);

        $es = new class extends \yii\elasticsearch\Connection {
            public $dslVersion = '7.10';
            public $mockCommand;
            public $mockBulk;
            public function createCommand($config = []) { return $this->mockCommand; }
            public function createBulkCommand($config = []) { return $this->mockBulk; }
            public function getDslVersion() { return $this->dslVersion; }
            public function open() {}
        };
        $es->mockCommand = $this->esCommand;
        $es->mockBulk = $this->getMockBuilder(\yii\elasticsearch\BulkCommand::class)->disableOriginalConstructor()->getMock();
        Yii::$app->set('elasticsearch', $es);

        // Mock MongoDB
        $this->mongoCollection = $this->getMockBuilder(\yii\mongodb\Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['remove', 'insert', 'createIndex', 'count'])
            ->getMock();
        $this->mongoCollection->method('insert')->willReturn(true);

        $mongodb = $this->getMockBuilder(\yii\mongodb\Connection::class)
            ->onlyMethods(['getCollection'])
            ->getMock();
        $mongodb->method('getCollection')->willReturn($this->mongoCollection);
        Yii::$app->set('mongodb', $mongodb);

        // Mock Queue
        $queue = $this->getMockBuilder(\yii\queue\sync\Queue::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['push'])
            ->getMock();
        Yii::$app->set('queue', $queue);
    }

    public function testActionCsvSuccess()
    {
        $exitCode = $this->controller->actionCsv('tests/_data/import_test.csv');
        
        verify($exitCode)->equals(ExitCode::OK);
        verify($this->controller->getBuffer())->stringContainsString('Import finished!');
        verify($this->controller->getBuffer())->stringContainsString('Total processed: 2');
    }

    public function testActionCsvFileNotFound()
    {
        $exitCode = $this->controller->actionCsv('non-existing.csv');
        
        verify($exitCode)->equals(ExitCode::UNSPECIFIED_ERROR);
        verify($this->controller->getBuffer())->stringContainsString('File not found');
    }

    public function testActionCsvQueue()
    {
        $exitCode = $this->controller->actionCsvQueue('tests/_data/import_test.csv');
        
        verify($exitCode)->equals(ExitCode::OK);
        verify($this->controller->getBuffer())->stringContainsString('jobs pushed to queue');
    }

    /*
    public function testActionStats()
    {
        // Mock ES count and search response
        $this->esCommand->method('search')->willReturnOnConsecutiveCalls(
            ['hits' => ['total' => ['value' => 2]]], // for count()
            ['hits' => [ // for all()
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => 'hash1', '_source' => ['company' => 'Comp1', 'product_name' => 'Prod1']],
                    ['_id' => 'hash2', '_source' => ['company' => 'Comp2', 'product_name' => 'Prod2']],
                ]
            ]]
        );
        
        $this->controller->actionStats();
        verify($this->controller->getBuffer())->stringContainsString('Total documents in ElasticSearch: 2');
        verify($this->controller->getBuffer())->stringContainsString('Last 5 documents');
    }
    */

    public function testActionReport()
    {
        // Mock report data
        $this->esCommand->method('search')->willReturn([
            'aggregations' => [
                'by_region' => [
                    'buckets' => [
                        [
                            'key' => 'ВИННИЦКАЯ',
                            'by_product' => [
                                'buckets' => [
                                    [
                                        'key' => 'Test Product',
                                        'total_quantity' => ['value' => 10]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->controller->actionReport();
        verify($this->controller->getBuffer())->stringContainsString('REGION');
        verify($this->controller->getBuffer())->stringContainsString('PRODUCT');
        verify($this->controller->getBuffer())->stringContainsString('ВИННИЦКАЯ');
    }

    public function testActionClear()
    {
        $this->controller->actionCsv('tests/_data/import_test.csv');
        $this->controller->actionClear();
        
        verify($this->controller->getBuffer())->stringContainsString('Index cleared');
    }
}

/**
 * Helper class to capture output
 */
class BufferedImportController extends ImportController
{
    private string $buffer = '';

    public function stdout($string)
    {
        $this->buffer .= $string;
        return strlen($string);
    }

    public function stderr($string)
    {
        $this->buffer .= $string;
        return strlen($string);
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function clearBuffer(): void
    {
        $this->buffer = '';
    }
}
