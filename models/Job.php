<?php

Yii::import('application.modules.yii-job.models._base.*');
Yii::import('application.modules.yii-job.models.*');
Yii::import('application.modules.yii-job.vendors.crontab.*');

class Job extends BaseJob
{
	protected $_finishMessage;
	
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
	
	public function rules() {
		return array_merge(parent::rules(), array(
			array('crontab', 'validateCrontab')
		));
	}
	
	public function validateCrontab($attribute, $params)
	{
		if ($this->crontab)
		{
			try 
			{
				$cron = CronExpression::factory($this->crontab);	
			} 
			catch (InvalidArgumentException $e) 
			{
				$this->addError('crontab', 'crontab notation parsing failed');
			}
		}
	}
	
	protected function timestampToDatabaseDate($timestamp = null)
	{
		if ($timestamp === null)
		{
			$timestamp = time();
		}
		
		return date("Y-m-d G:i:s", $timestamp);
	}
	
	public function calculateNextPlannedTime()
	{
		if ($this->crontab)
		{
			$cron = CronExpression::factory($this->crontab);
			$this->planned_time = $this->timestampToDatabaseDate($cron->getNextRunDate()->getTimestamp());
		}		
	}
	
	protected function beforeValidate()
	{
		if ($this->planned_time === null)
		{
			$this->calculateNextPlannedTime();
			if ($this->planned_time === null)
				$this->planned_time = $this->timestampToDatabaseDate();
		}
		
		return parent::beforeValidate();
	}
	
	public function beforeSave()
	{
		$this->update_time = $this->timestampToDatabaseDate();
		if (!$this->create_time)
		{
			$this->create_time = $this->update_time;
		}
		$this->job_data = json_encode($this->getJobData());
		
		Yii::trace("job data in before save {$this->id}: {$this->job_data}");		
		
		return parent::beforeSave();
	}
	
	public function afterConstruct()
	{
		if ($this->scenario != 'search')
		{
			$this->job_class = get_class($this);
			$this->job_status_id = JobStatus::ENQUEUED;			
		}
		parent::afterConstruct();
	}
	
	protected function afterFind()
	{
		Yii::trace("Decoding {$this->job_data}");
		$this->setJobData(json_decode($this->job_data, true));
		parent::afterFind();
	}
	
	protected function instantiate($attributes)
	{
		if ($class = $attributes['job_class'])
		{
			return new $class(null);
		}
		else
			return parent::instantiate($attributes);
	}
	
	public function beforeExecute()
	{
		$this->start_time = $this->timestampToDatabaseDate();
		$this->job_status_id = JobStatus::RUNNING;
		$this->save();			
	}
	
	protected function _execute()
	{
		throw new Exception("Illegal call to _execute. This method should have been overwritten");
	}
	
	/**
	 * @return boolean Should return true when the job finished regularly
	 */
	public function execute()
	{
		Yii::trace("execute");
		$this->beforeExecute();
		try 
		{
			$result = $this->_execute();
			
			//set default rersult when child execute did not set another status than RUNNING
			if ($this->job_status_id == JobStatus::RUNNING)
			{				
				$this->job_status_id = $result ? JobStatus::SUCCESS : JobStatus::ERROR;
			}
		}
		catch (Exception $e)
		{
			Yii::trace("catch exception");
			$this->job_status_id = JobStatus::EXCEPTION;
			$this->_finishMessage = array(
				'job_data' => $this->job_data,
				'message' => $e->getMessage(),
				'trace' => $e->getTrace()
			); 	
			Yii::log("Exception during job (ID {$this->id}) run: ".$e->getMessage(), 'error');
		}
		
		Yii::trace("after execute");
		$this->afterExecute();
	}
	
	protected function logResult()
	{
		Yii::trace("log result");
		$log = new JobLog();
		$log->job_class = $this->job_class;
		$log->start_time = $this->start_time;
		$log->job_status_id = $this->job_status_id;
		$log->finish_time = $this->timestampToDatabaseDate();
		$log->finish_message = json_encode($this->getFinishMessage());
		$log->create_time = $this->timestampToDatabaseDate();
		if (!$log->save())
		{
			Yii::log('Saving a job log failed: '.print_r($log->errors), 'error');
		}
		else 
		{
			if ($log->job_status_id == JobStatus::EXCEPTION || $log->job_status_id == JobStatus::ERROR)
			{
				Yii::log("Running job {$log->job_class} resulted in an exception or error. Please see log ID {$log->id} for details", 'error');
			}
		}
	}
	
	public function afterExecute()
	{
		$this->logResult();
		
		$this->planned_time = null;
		$this->calculateNextPlannedTime();
		
		if ($this->crontab)
		{
			$this->calculateNextPlannedTime();
			$this->start_time = null;
			$this->job_status_id = JobStatus::ENQUEUED;
			$this->save();
		}
		else
		{ 
			//Yii::trace("job deleted");
			$this->delete();
		}
	}
	
	/**
	 * @return mixed Any data that should be stored in the job log table
	 */
	public function getFinishMessage()
	{
		return $this->_finishMessage;
	}
	
	/**
	 * @return mixed Any data, handling in child class
	 */
	public function getJobData()
	{
		
	}

	/**
	 * 
	 * @param mixed $data Any data. Default implementation expects an array of model attributes
	 */
	public function setJobData($data)
	{		
		
		if (!is_array($data))
			return;

		Yii::trace("class ".get_class($this)."setting array: ".print_r($data, true));
		
		foreach ($data as $attribute => $value)
		{
			$this->$attribute = $value;
		}
	}
	
	/**
	 * 
	 * @param Job $job
	 * @return boolean True when the given job is considered as duplicate
	 */
	public function isDuplicateOf($job)
	{
		return false;
	}
	
	public function abort()
	{
		$this->job_status_id = JobStatus::ERROR;
		$this->_finishMessage = array('message' => "Job aborted", 'job_data' => $this->job_data);
		$this->afterExecute();
	}
}