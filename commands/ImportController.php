<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\mongodb\Exception;

/**
 * Command for importing medical data from CSV to MongoDB.
 */
class ImportController extends Controller
{
    /**
     * Mapping from Russian CSV headers to English snake_case field names.
     */
    private const HEADER_MAPPING = [
        'Фирма'                   => 'company',
        'Область'                 => 'region',
        'Город'                   => 'city',
        'Дата накл'               => 'invoice_date',
        'Факт.адрес доставки'     => 'delivery_address',
        'Юр. адрес клиента'       => 'client_legal_address',
        'Клиент'                  => 'client',
        'Код клиента'             => 'client_code',
        'Код подразд кл'          => 'client_subdivision_code',
        'ОКПО клиента'            => 'client_okpo',
        'Лицензия'                => 'license',
        'Дата окончания лицензии' => 'license_expiry_date',
        'Код товара'              => 'product_code',
        'Штрих-код товара'        => 'barcode',
        'Товар'                   => 'product_name',
        'Код мориона'             => 'morion_code',
        'ЕИ'                      => 'unit',
        'Производитель'           => 'manufacturer',
        'Поставщик'               => 'supplier',
        'Количество'              => 'quantity',
        'Склад/филиал'            => 'warehouse_branch',
    ];

    /**
     * Imports CSV file into MongoDB collection.
     *
     * @param string $filePath Path to CSV file.
     * @return int Exit code.
     * @throws Exception
     */
    public function actionCsv(string $filePath): int
    {
        if (!file_exists($filePath)) {
            $this->stderr("File not found: {$filePath}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->stderr("Failed to open file: {$filePath}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Read headers
        $headers = fgetcsv($handle, 0, ",", "\"", "");
        if ($headers === false) {
            $this->stderr("Failed to read headers from CSV\n", Console::FG_RED);
            fclose($handle);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Map headers to English snake_case
        $mappedHeaders = [];
        foreach ($headers as $header) {
            $mappedHeaders[] = self::HEADER_MAPPING[$header] ?? $header;
        }

        /** @var \yii\mongodb\Connection $mongodb */
        $mongodb    = Yii::$app->mongodb;
        $collection = $mongodb->getCollection('medical_data');

        // Create a unique index on the row hash to prevent duplicates
        $collection->createIndex(['row_hash' => 1], ['unique' => true]);

        $insertedCount  = 0;
        $duplicateCount = 0;
        $totalProcessed = 0;

        $this->stdout("Starting import...\n", Console::FG_CYAN);

        while (($row = fgetcsv($handle, 0, ",", "\"", "")) !== false) {
            // Skip empty rows or rows with incorrect column count
            if (empty(array_filter($row)) || count($row) !== count($headers)) {
                continue;
            }

            $totalProcessed++;
            $data = array_combine($mappedHeaders, $row);

            // Generate row hash for uniqueness check
            $rowHash          = md5(serialize($data));
            $data['row_hash'] = $rowHash;

            try {
                $collection->insert($data);
                $insertedCount++;
            } catch (\yii\mongodb\Exception $e) {
                // If error is related to duplicate key, increment duplicate counter
                if (strpos($e->getMessage(), 'duplicate key error') !== false) {
                    $duplicateCount++;
                } else {
                    $this->stderr("\nError inserting row {$totalProcessed}: " . $e->getMessage() . "\n", Console::FG_RED);
                }
            }

            if ($totalProcessed % 100 === 0) {
                $this->stdout("Processed rows: {$totalProcessed}\r");
            }
        }

        fclose($handle);

        $this->stdout("\nImport completed.\n", Console::FG_GREEN);
        $this->stdout("Total processed: {$totalProcessed}\n");
        $this->stdout("Successfully added: {$insertedCount}\n", Console::FG_GREEN);
        $this->stdout("Duplicates skipped: {$duplicateCount}\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }
}
