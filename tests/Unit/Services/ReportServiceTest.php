<?php

namespace app\tests\Unit\Services;

use app\services\ReportService;
use app\models\InvoiceElastic;
use Yii;

class ReportServiceTest extends \Codeception\Test\Unit
{
    private ReportService $service;

    protected function _before()
    {
        $this->service = new ReportService();
        
        // Mock ES
        $command = $this->getMockBuilder(\yii\elasticsearch\Command::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();
        
        $command->method('search')->willReturn([
            'aggregations' => [
                'by_region' => [
                    'buckets' => [
                        [
                            'key' => 'Region1',
                            'by_product' => [
                                'buckets' => [
                                    [
                                        'key' => 'ProductA',
                                        'total_quantity' => ['value' => 15]
                                    ],
                                    [
                                        'key' => 'ProductB',
                                        'total_quantity' => ['value' => 7]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'key' => 'Region2',
                            'by_product' => [
                                'buckets' => [
                                    [
                                        'key' => 'ProductA',
                                        'total_quantity' => ['value' => 3]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $es = $this->getMockBuilder(\yii\elasticsearch\Connection::class)
            ->onlyMethods(['createCommand'])
            ->getMock();
        $es->method('createCommand')->willReturn($command);
        
        Yii::$app->set('elasticsearch', $es);
    }

    public function testGetRegionProductQuantityData()
    {
        $data = $this->service->getRegionProductQuantityData();
        
        verify($data)->notEmpty();
        verify(count($data))->equals(3); // (R1, PA), (R1, PB), (R2, PA)
        
        // Check specific values
        $found = false;
        foreach ($data as $row) {
            if ($row['region'] === 'Region1' && $row['product'] === 'ProductA') {
                verify($row['quantity'])->equals(15);
                $found = true;
            }
        }
        verify($found)->true();
    }
}
