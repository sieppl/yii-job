<?php

Yii::import('application.modules.job.models._base.BaseJobLog');

class JobLog extends BaseJobLog
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}