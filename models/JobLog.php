<?php

Yii::import('application.modules.yii-job.models._base.BaseJobLog');

class JobLog extends BaseJobLog
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}