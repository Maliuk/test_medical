<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var app\models\UploadForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Импорт CSV';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="import-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Выберите CSV файл для импорта данных в систему (MongoDB + ElasticSearch):</p>

    <div class="row">
        <div class="col-lg-5">
            <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

                <?= $form->field($model, 'csvFile')->fileInput() ?>

                <?= $form->field($model, 'useQueue')->checkbox() ?>

                <div class="form-group">
                    <?= Html::submitButton('Запустить импорт', ['class' => 'btn btn-primary', 'name' => 'import-button']) ?>
                </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
