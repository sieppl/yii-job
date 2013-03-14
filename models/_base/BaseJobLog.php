<?php

/**
 * This is the model base class for the table "job_log".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "JobLog".
 *
 * Columns in table "job_log" available as properties of the model,
 * and there are no model relations.
 *
 * @property string $id
 * @property string $job_class
 * @property string $start_time
 * @property string $finish_time
 * @property integer $job_status_id
 * @property string $finish_message
 * @property string $create_time
 * @property string $update_time
 *
 */
abstract class BaseJobLog extends CActiveRecord {

    public static function model($className=__CLASS__) {
        return parent::model($className);
    }

    public function tableName() {
        return 'job_log';
    }

    public static function label($n = 1) {
        return Yii::t('app', 'JobLog|JobLogs', $n);
    }

    public static function representingColumn() {
        return 'job_class';
    }

    public function rules() {
        return array(
            array('job_class, start_time, finish_time, job_status_id', 'required'),
            array('job_status_id', 'numerical', 'integerOnly'=>true),
            array('job_class', 'length', 'max'=>64),
            array('finish_message, create_time, update_time', 'safe'),
            array('finish_message, create_time, update_time', 'default', 'setOnEmpty' => true, 'value' => null),
            array('id, job_class, start_time, finish_time, job_status_id, finish_message, create_time, update_time', 'safe', 'on'=>'search'),
        );
    }

    public function relations() {
        return array(
        );
    }

    public function pivotModels() {
        return array(
        );
    }

    public function attributeLabels() {
        return array(
            'id' => Yii::t('app', 'ID'),
            'job_class' => Yii::t('app', 'Job Class'),
            'start_time' => Yii::t('app', 'Start Time'),
            'finish_time' => Yii::t('app', 'Finish Time'),
            'job_status_id' => Yii::t('app', 'Job Status'),
            'finish_message' => Yii::t('app', 'Finish Message'),
            'create_time' => Yii::t('app', 'Create Time'),
            'update_time' => Yii::t('app', 'Update Time'),
        );
    }

    public function search() {
        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id, true);
        $criteria->compare('job_class', $this->job_class, true);
        $criteria->compare('start_time', $this->start_time, true);
        $criteria->compare('finish_time', $this->finish_time, true);
        $criteria->compare('job_status_id', $this->job_status_id);
        $criteria->compare('finish_message', $this->finish_message, true);
        $criteria->compare('create_time', $this->create_time, true);
        $criteria->compare('update_time', $this->update_time, true);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }
}