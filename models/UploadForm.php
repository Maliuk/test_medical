<?php

namespace app\models;

use yii\base\Model;
use yii\web\UploadedFile;

/**
 * UploadForm is the model behind the upload form.
 */
class UploadForm extends Model
{
    /**
     * @var UploadedFile
     */
    public $csvFile;

    /**
     * @var bool whether to use queue for import
     */
    public $useQueue = false;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['csvFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'csv'],
            [['useQueue'], 'boolean'],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'csvFile' => 'CSV Файл',
            'useQueue' => 'Использовать очередь',
        ];
    }
}
