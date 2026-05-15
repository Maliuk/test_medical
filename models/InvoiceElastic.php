<?php

namespace app\models;

use yii\elasticsearch\ActiveRecord;

/**
 * MedicalElastic model for ElasticSearch.
 *
 * @property string $row_hash
 * @property string $company
 * @property string $region
 * @property string $city
 * @property string $product_name
 * @property string $manufacturer
 * @property string $quantity
 */
class InvoiceElastic extends ActiveRecord
{
    /**
     * @return string the name of the index this record is stored in.
     */
    public static function index()
    {
        return 'medical_data';
    }

    /**
     * @return string the name of the type this record is stored in.
     * For ElasticSearch 7+, it's recommended to use null or empty string if types are not used.
     */
    public static function type()
    {
        return null;
    }

    /**
     * @return array the list of attributes for this record
     */
    public function attributes()
    {
        return [
            'row_hash',
            'company',
            'region',
            'city',
            'product_name',
            'manufacturer',
            'quantity',
        ];
    }


    /**
     * Create index with mapping
     */
    public static function createIndex(): void
    {
        $db = static::getDb();
        $command = $db->createCommand();

        if (!$command->indexExists(static::index())) {
            $command->createIndex(static::index(), [
                'mappings' => [
                    'properties' => [
                        'row_hash'     => ['type' => 'keyword'],
                        'company'      => ['type' => 'text'],
                        'region'       => ['type' => 'keyword'],
                        'city'         => ['type' => 'keyword'],
                        'product_name' => ['type' => 'keyword'],
                        'manufacturer' => ['type' => 'text'],
                        'quantity'     => ['type' => 'integer'],
                    ],
                ],
            ]);
        }
    }
}
