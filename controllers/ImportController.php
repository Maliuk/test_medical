<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\UploadedFile;
use app\models\UploadForm;
use app\services\InvoiceImportService;
use app\services\ReportService;

class ImportController extends Controller
{
    /**
     * Displays the upload form.
     *
     * @return string
     */
    public function actionIndex()
    {
        $model = new UploadForm();

        if (Yii::$app->request->isPost) {
            $model->csvFile = UploadedFile::getInstance($model, 'csvFile');
            $model->useQueue = Yii::$app->request->post('UploadForm')['useQueue'] ?? false;

            if ($model->validate()) {
                $filePath = Yii::getAlias('@runtime/uploads/') . $model->csvFile->baseName . '.' . $model->csvFile->extension;
                if (!is_dir(dirname($filePath))) {
                    mkdir(dirname($filePath), 0777, true);
                }

                if ($model->csvFile->saveAs($filePath)) {
                    $service = new InvoiceImportService();
                    try {
                        if ($model->useQueue) {
                            $jobsCount = $service->importCsvToQueue($filePath);
                            Yii::$app->session->setFlash('success', "Файл успешно поставлен в очередь на импорт. Создано {$jobsCount} заданий.");
                        } else {
                            $results = $service->importCsv($filePath);
                            Yii::$app->session->setFlash('success', "Импорт завершен успешно! Обработано строк: {$results['total']}, добавлено: {$results['inserted']}, дубликатов: {$results['duplicates']}.");
                        }
                    } catch (\Exception $e) {
                        Yii::$app->session->setFlash('error', "Ошибка при импорте: " . $e->getMessage());
                    }

                    // Delete temp file if not using queue or after processing?
                    // For queue, we might need to keep it or copy it.
                    // Actually, InvoiceImportService::importCsvToQueue reads it immediately and pushes chunks to queue,
                    // so we can delete it after importCsvToQueue returns.
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }

                    return $this->refresh();
                }
            }
        }

        return $this->render('index', [
            'model' => $model,
        ]);
    }

    /**
     * Displays the report.
     *
     * @return string
     */
    public function actionReport()
    {
        $service = new ReportService();
        $data = $service->getRegionProductQuantityData();

        return $this->render('report', [
            'data' => $data,
        ]);
    }
}
