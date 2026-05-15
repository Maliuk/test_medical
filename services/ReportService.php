<?php

namespace app\services;

use app\models\InvoiceElastic;

/**
 * Service for generating reports.
 */
class ReportService
{
    /**
     * Gets report data grouped by region and product with summed quantity.
     *
     * @return array
     */
    public function getRegionProductQuantityData(): array
    {
        $db = InvoiceElastic::getDb();
        $command = $db->createCommand();
        $command->index = InvoiceElastic::index();
        $command->queryParts = [
            'size' => 0, // We only need aggregations
            'aggs' => [
                'by_region' => [
                    'terms' => [
                        'field' => 'region',
                        'size'  => 50,
                    ],
                    'aggs' => [
                        'by_product' => [
                            'terms' => [
                                'field' => 'product_name',
                                'size'  => 100,
                            ],
                            'aggs' => [
                                'total_quantity' => [
                                    'sum' => [
                                        'field' => 'quantity',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $results = $command->search();
        $buckets = $results['aggregations']['by_region']['buckets'] ?? [];

        $reportData = [];
        foreach ($buckets as $regionBucket) {
            $region = $regionBucket['key'];
            $productBuckets = $regionBucket['by_product']['buckets'] ?? [];

            foreach ($productBuckets as $productBucket) {
                $reportData[] = [
                    'region'   => $region,
                    'product'  => $productBucket['key'],
                    'quantity' => $productBucket['total_quantity']['value'] ?? 0,
                ];
            }
        }

        return $reportData;
    }
}
