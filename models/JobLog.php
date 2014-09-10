<?php

Yii::import('application.modules.yii-job.models._base.BaseJobLog');

class JobLog extends BaseJobLog implements JobInterface
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
	
	public function beforeSave()
	{
		$this->finish_message = json_encode($this->finish_message);
	
		return parent::beforeSave();
	}
	
	protected function afterFind()
	{
		$this->finish_message = json_decode($this->finish_message, true);
		parent::afterFind();
	}
	
	public function getJobId()
	{
		return $this->job_id;
	}
	
	public function getJobToken()
	{
		return $this->token;
	}
	
	public function getJobData()
	{
		return json_decode($this->job_data, true);
	}
	
	public function getJobStatusId()
	{
		return $this->job_status_id;
	}
	
	public function getJobProgress()
	{
		return $this->progress;
	}
	
	public function getJobResult()
	{
		return $this->finish_message;
	}
	
	public function getJobInfo()
	{
		return new JobInfo($this);
	}
}