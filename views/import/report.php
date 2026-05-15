<?php

/** @var yii\web\View $this */
/** @var array $data */

use yii\bootstrap5\Html;

$this->title = 'Отчет по остаткам';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="import-report">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Агрегированные данные из ElasticSearch (группировка по области и товару):</p>

    <?php if (empty($data)): ?>
        <div class="alert alert-info">Нет данных для отображения.</div>
    <?php else: ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Область</th>
                    <th>Товар</th>
                    <th>Количество</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= Html::encode($row['region']) ?></td>
                        <td><?= Html::encode($row['product']) ?></td>
                        <td><?= Html::encode($row['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
