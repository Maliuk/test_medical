<?php

namespace app\commands;

use app\models\InvoiceElastic;
use app\services\InvoiceImportService;
use app\services\ReportService;
use yii\console\Controller;
use yii\console\ExitCode;
use Yii;

/**
 * Controller for testing medical data import to ElasticSearch.
 */
class ImportController extends Controller
{
    /**
     * Imports CSV file to MongoDB and ElasticSearch.
     * @param string $file Path to CSV file.
     * @return int
     */
    public function actionCsv(string $file = 'medical.csv'): int
    {
        $filePath = Yii::getAlias('@app/' . $file);
        if (!file_exists($filePath)) {
            $this->stderr("File not found: {$filePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $service = new InvoiceImportService();
        $this->stdout("Starting import from {$file}...\n");

        try {
            $results = $service->importCsv($filePath, function($processed) {
                $this->stdout("Processed {$processed} rows...\n");
            });

            $this->stdout("Import finished!\n");
            $this->stdout("Total processed: {$results['total']}\n");
            $this->stdout("Inserted: {$results['inserted']}\n");
            $this->stdout("Duplicates (skipped in MongoDB): {$results['duplicates']}\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Imports CSV file using queue.
     * @param string $file Path to CSV file.
     * @return int
     */
    public function actionCsvQueue(string $file = 'medical.csv'): int
    {
        $filePath = Yii::getAlias('@app/' . $file);
        if (!file_exists($filePath)) {
            $this->stderr("File not found: {$filePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $service = new InvoiceImportService();
        $this->stdout("Pushing import jobs to queue for {$file}...\n");

        try {
            $jobsCount = $service->importCsvToQueue($filePath);
            $this->stdout("Done! {$jobsCount} jobs pushed to queue.\n");
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Shows statistics from ElasticSearch.
     */
    public function actionStats(): void
    {
        try {
            $count = InvoiceElastic::find()->count();
            $this->stdout("Total documents in ElasticSearch: {$count}\n");

            if ($count > 0) {
                $this->stdout("Last 5 documents:\n");
                $docs = InvoiceElastic::find()->limit(5)->all();
                foreach ($docs as $doc) {
                    $this->stdout("- ID: {$doc->getPrimaryKey()}, Company: {$doc->company}, Product: {$doc->product_name}\n");
                }
            }
        } catch (\Exception $e) {
            $this->stderr("Error connecting to ElasticSearch: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Generates a report grouped by region and product, summing the quantity.
     */
    public function actionReport(): void
    {
        try {
            $service = new ReportService();
            $reportData = $service->getRegionProductQuantityData();

            if (empty($reportData)) {
                $this->stdout("No data available for report.\n");
                return;
            }

            $this->stdout(str_pad("REGION", 20) . " | " . str_pad("PRODUCT", 60) . " | " . "QUANTITY\n");
            $this->stdout(str_repeat("-", 100) . "\n");

            foreach ($reportData as $row) {
                $this->stdout(
                    $this->pad($row['region'], 20) . " | " .
                    $this->pad($row['product'], 60) . " | " .
                    $row['quantity'] . "\n"
                );
            }
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Helper to pad multibyte strings.
     */
    private function pad(string $string, int $length): string
    {
        $strLen = mb_strlen($string);
        if ($strLen >= $length) {
            return mb_substr($string, 0, $length - 3) . '...';
        }
        return $string . str_repeat(' ', $length - $strLen);
    }

    /**
     * Clears ElasticSearch index.
     */
    public function actionClear(): void
    {
        try {
            InvoiceElastic::getDb()->createCommand()->deleteIndex(InvoiceElastic::index());
            $this->stdout("Index cleared.\n");
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
        }
    }
}
