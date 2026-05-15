<?php

namespace app\services;

use Yii;
use app\jobs\ImportChunkJob;
use app\models\InvoiceElastic;
use yii\mongodb\Collection;
use yii\mongodb\Connection;
use yii\mongodb\Exception;

/**
 * Service for importing medical data.
 */
class InvoiceImportService
{
    /**
     * Mapping from Russian CSV headers to English snake_case field names.
     */
    private const array HEADER_MAPPING = [
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
     * @param callable|null $onProgress Callback for progress tracking: function(int $processed)
     * @param callable|null $onError Callback for error handling: function(string $message, int $rowNumber)
     * @return array Import statistics.
     * @throws Exception
     * @throws \Exception
     */
    public function importCsv(string $filePath, ?callable $onProgress = null, ?callable $onError = null): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception("Failed to open file: {$filePath}");
        }

        // Read headers
        $headers = fgetcsv($handle, 0, ",", "\"", "");
        if ($headers === false) {
            fclose($handle);
            throw new \Exception("Failed to read headers from CSV");
        }

        $mappedHeaders = $this->mapHeaders($headers);

        $insertedCount  = 0;
        $duplicateCount = 0;
        $totalProcessed = 0;

        while (($row = fgetcsv($handle, 0, ",", "\"", "")) !== false) {
            if ($this->isRowInvalid($row, $headers)) {
                continue;
            }

            $totalProcessed++;
            $data = $this->prepareRowData($row, $mappedHeaders);

            try {
                if ($this->insertRow($data)) {
                    $insertedCount++;
                } else {
                    $duplicateCount++;
                }
                // Always sync with ElasticSearch, it will handle duplicates by _id (row_hash)
                $this->importToElastic([$data]);
            } catch (Exception $e) {
                if ($onError) {
                    $onError($e->getMessage(), $totalProcessed);
                } else {
                    fclose($handle);
                    throw $e;
                }
            }

            if ($onProgress && $totalProcessed % 100 === 0) {
                $onProgress($totalProcessed);
            }
        }

        fclose($handle);

        return [
            'total'      => $totalProcessed,
            'inserted'   => $insertedCount,
            'duplicates' => $duplicateCount,
        ];
    }

    /**
     * Imports CSV file using chunks and queues.
     *
     * @param string $filePath
     * @param int $chunkSize
     * @return int Total number of jobs pushed to queue.
     * @throws \Exception
     */
    public function importCsvToQueue(string $filePath, int $chunkSize = 100): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception("Failed to open file: {$filePath}");
        }

        $headers = fgetcsv($handle, 0, ",", "\"", "");
        if ($headers === false) {
            fclose($handle);
            throw new \Exception("Failed to read headers from CSV");
        }

        $mappedHeaders = $this->mapHeaders($headers);
        $chunk         = [];
        $jobsCount     = 0;
        $rowNumber     = 1;

        while (($row = fgetcsv($handle, 0, ",", "\"", "")) !== false) {
            $rowNumber++;
            if ($this->isRowInvalid($row, $headers)) {
                Yii::warning("Invalid row structure at line {$rowNumber}", 'import');
                continue;
            }

            $data = $this->prepareRowData($row, $mappedHeaders);
            $validationErrors = $this->validateRow($data);

            if (!empty($validationErrors)) {
                Yii::error("Row validation failed at line {$rowNumber}: " . implode(', ', $validationErrors), 'import');
                continue;
            }

            $chunk[] = $data;

            if (count($chunk) >= $chunkSize) {
                $this->pushChunkToQueue($chunk);
                $jobsCount++;
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $this->pushChunkToQueue($chunk);
            $jobsCount++;
        }

        fclose($handle);

        Yii::info("Import finished. Total jobs pushed: {$jobsCount}", 'import');

        return $jobsCount;
    }

    /**
     * Imports a chunk of data using batch insert.
     *
     * @param array $chunk
     * @return void
     * @throws Exception
     */
    public function importChunk(array $chunk): void
    {
        if (empty($chunk)) {
            return;
        }

        /** @var Connection $mongodb */
        $mongodb    = Yii::$app->mongodb;
        $collection = $mongodb->getCollection('invoices');

        $this->ensureIndexes($collection);

        try {
            $collection->batchInsert($chunk, ['ordered' => false]);
            $this->importToElastic($chunk);
            Yii::info("Chunk of " . count($chunk) . " records successfully processed.", 'import');
        } catch (Exception $e) {
            // Some records might have been inserted even if an exception occurred (due to ordered => false)
            Yii::warning("Batch insert completed with some notices/errors: " . $e->getMessage(), 'import');
        }
    }

    /**
     * Ensures necessary indexes exist in MongoDB.
     * @param Collection $collection
     * @throws Exception
     */
    private function ensureIndexes(Collection $collection): void
    {
        $collection->createIndex(['row_hash' => 1], ['unique' => true]);
    }

    /**
     * Validates row data.
     *
     * @param array $data
     * @return array List of error messages.
     */
    private function validateRow(array $data): array
    {
        $errors = [];

        // Basic validation: required fields
        $requiredFields = ['product_name', 'company', 'quantity'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field]) && $data[$field] !== '0') {
                $errors[] = "Field '{$field}' is required";
            }
        }

        return $errors;
    }

    /**
     * Maps CSV headers to snake_case.
     */
    private function mapHeaders(array $headers): array
    {
        $mapped = [];
        foreach ($headers as $header) {
            $mapped[] = self::HEADER_MAPPING[$header] ?? $header;
        }

        return $mapped;
    }

    /**
     * Checks if row is empty or has incorrect column count.
     */
    private function isRowInvalid(array $row, array $headers): bool
    {
        return empty(array_filter($row)) || count($row) !== count($headers);
    }

    /**
     * Prepares row data for insertion.
     */
    private function prepareRowData(array $row, array $mappedHeaders): array
    {
        $data = array_combine($mappedHeaders, $row);
        $data['row_hash'] = md5(serialize($data));

        return $data;
    }

    /**
     * Pushes a chunk to the queue.
     */
    private function pushChunkToQueue(array $chunk): void
    {
        Yii::$app->queue->push(new ImportChunkJob([
            'data' => $chunk,
        ]));
    }

    /**
     * Inserts a single row into MongoDB.
     *
     * @param array $data
     * @return bool True if inserted, false if duplicate.
     * @throws Exception
     */
    private function insertRow(array $data): bool
    {
        /** @var Connection $mongodb */
        $mongodb    = Yii::$app->mongodb;
        $collection = $mongodb->getCollection('invoices');

        // Ensure index exists
        $collection->createIndex(['row_hash' => 1], ['unique' => true]);

        try {
            $collection->insert($data);

            return true;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'duplicate key error')) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Imports data to ElasticSearch.
     *
     * @param array $chunk
     */
    private function importToElastic(array $chunk): void
    {
        InvoiceElastic::createIndex();
        $db = InvoiceElastic::getDb();
        $bulkCommand = $db->createBulkCommand();

        foreach ($chunk as $data) {
            $bulkCommand->addAction(['index' => ['_index' => InvoiceElastic::index(), '_id' => $data['row_hash']]], [
                'row_hash'     => $data['row_hash'],
                'company'      => $data['company'] ?? '',
                'region'       => $data['region'] ?? '',
                'city'         => $data['city'] ?? '',
                'product_name' => $data['product_name'] ?? '',
                'manufacturer' => $data['manufacturer'] ?? '',
                'quantity'     => (int)($data['quantity'] ?? 0),
            ]);
        }

        try {
            $bulkCommand->execute();
        } catch (\Exception $e) {
            Yii::error("Bulk insert to ElasticSearch failed: " . $e->getMessage(), 'import');
        }
    }
}
