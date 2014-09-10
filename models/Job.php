<?php

Yii::import('application.modules.yii-job.models._base.*');
Yii::import('application.modules.yii-job.models.*');
Yii::import('application.modules.yii-job.vendors.crontab.*');

class Job extends BaseJob implements JobInterface
{
	protected static $logClass = "application.models.Job";

	protected $_finishMessage;
	
	public $progressTotalCount;
	public $progressCurrentCount;
	public $lastProgressUpdate = null;
	public $progressUpdateDelay = 1; // 1 second
	public $jobProgressType = JobProgressType::ENDLESS;
	public $rollbackOpenTransaction = true;

	/**
	 * If set to true, then the progress is saved in the database
	 *
	 * @var bool
	 */
	public $persist = true;
	
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
	
	public function rules()
	{
		return array_merge(parent::rules(), array(
			array('crontab', 'validateCrontab')
		));
	}

    public function identifiers()
    {
        return array();
    }
	
	public function getJobId()
	{
		return $this->id;
	}
	
	public function getJobToken()
	{
		return $this->token;
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
		return null;
	}
	
	public function getJobInfo()
	{
		return new JobInfo($this);
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
	
	public function startNow()
	{
		$this->planned_time = $this->timestampToDatabaseDate(time());
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
        $jobData = $this->getJobData();
        $identifiers = array_flip($this->identifiers());
        if($identifiers && is_array($jobData))
        {
            foreach ($jobData as $name => $value)
            {
                if (isset($identifiers[$name]))
                {
                    $column = $identifiers[$name];
                    $this->$column = $value;
                }
            }
        }

		$this->update_time = $this->timestampToDatabaseDate();
		if (!$this->create_time)
		{
			$this->create_time = $this->update_time;
		}
		$this->job_data = json_encode($jobData);
		
		//Yii::trace("job data in before save {$this->id}: {$this->job_data}");		
		
		return parent::beforeSave();
	}
	
	public function refreshToken()
	{
		$this->token = str_replace(".", "", uniqid('', true));
	}
	
	public function initializeProgress()
	{
		switch ($this->jobProgressType)
		{
			case JobProgressType::LINEAR:
				$this->progress = 0;
				break;
			default:
				$this->progress = -1;
				break;
		}
	}
	
	public function afterConstruct()
	{
		if ($this->scenario != 'search')
		{
			$this->job_class = get_class($this);
			$this->job_status_id = JobStatus::ENQUEUED;		
			$this->job_origin_id = JobOrigin::RUNTIME;	
			$this->refreshToken();
			$this->initializeProgress();
		}
		parent::afterConstruct();
	}
	
	protected function afterFind()
	{
		//Yii::trace("Decoding {$this->job_data}");
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

	protected function changeToRunningStatus()
	{
		$this->start_time = $this->timestampToDatabaseDate();
		$this->job_status_id = JobStatus::RUNNING;
	}
	
	public function beforeExecute()
	{
		$this->changeToRunningStatus();
		if (!$this->save())
			throw new Exception("Unable to save job in beforeExecute");
	}
	
	public function onSuccess()
	{
		
	}
	
	public function onError()
	{
	
	}
	
	public function _onError()
	{
		try
		{
			$this->onError();
		}
		catch (Exception $ex)
		{
			Yii::log("Exception during job onError (ID {$this->id}): ".$ex->getMessage(), CLogger::LEVEL_ERROR);
		}
	}

	/**
	 * @param CEvent $event
	 */
	public function onProgress($event)
	{
		$this->raiseEvent('onProgress', $event);
	}

	/**
	 * @param CEvent $event
	 */
	public function onProgressStart($event)
	{
		$this->raiseEvent('onProgressStart', $event);
	}

	/**
	 * @param CEvent $event
	 */
	public function onProgressEnd($event)
	{
		$this->raiseEvent('onProgressEnd', $event);
	}
	
	public function updateProgress($progress, $check = true)
	{
		$doUpdate = false;

		$this->progress = (int) $progress;

		if ($this->persist)
		{
			if (!$check)
				$doUpdate = true;
			else
			{
				if ($this->lastProgressUpdate === null)
					$doUpdate = true;
				else
				{
					if (time() >= ($this->lastProgressUpdate + $this->progressUpdateDelay))
						$doUpdate = true;
				}
			}

			if ($doUpdate)
			{
				$this->lastProgressUpdate = time();
				$this->save(true, array("progress"));
			}
		}
	}
	
	public function startProgress($totalCount)
	{
		Yii::trace("startProgress", self::$logClass);

		if ($this->jobProgressType != JobProgressType::LINEAR)
			return;
			
		$this->progressTotalCount = $totalCount;
		$this->progressCurrentCount = 0;
		$this->lastProgressUpdate = null;

		$progress = 0;

		$this->onProgressStart(new CEvent($this, array("progress" => $progress)));
		
		$this->doProgress($progress);
	}
	
	public function endProgress()
	{
		Yii::trace("endProgress", self::$logClass);

		if ($this->jobProgressType != JobProgressType::LINEAR)
			return;

		$progress = 100;

		$this->onProgressEnd(new CEvent($this, array("progress" => $progress)));
		
		$this->updateProgress($progress, false);
	}
	
	public function doProgress($currentCount = null)
	{
		//Yii::trace("doProgress", self::$logClass);

		if ($this->jobProgressType != JobProgressType::LINEAR)
			return;
		
		if ($currentCount)
			$this->progressCurrentCount = $currentCount;
		else
			$this->progressCurrentCount++;
		
		$progress = 0;
		if ($this->progressTotalCount)
		{
			$progress = ceil(($this->progressCurrentCount / $this->progressTotalCount) * 100);
		}
		
		if ($progress > 100)
			$progress = 100;
		elseif ($progress < 0)
			$progress = 0;

		$progress = (int) $progress;

		$this->onProgress(new CEvent($this, array("progress" => $progress)));
		
		$this->updateProgress($progress);
	}
	
	protected function _execute()
	{
		throw new Exception("Illegal call to _execute. This method should have been overwritten");
	}

    public function executeDirect()
    {
	    $this->changeToRunningStatus();
	    $this->persist = false;
	    $this->rollbackOpenTransaction = false;
        $this->execute();
	    return $this->job_status_id;
    }
	
	/**
	 * @return boolean Should return true when the job finished regularly
	 */
	public function execute()
	{
		Yii::trace("execute", self::$logClass);
		
		try 
		{
			$result = $this->_execute();
			
			//set default result when child execute did not set another status than RUNNING
			if ($this->job_status_id == JobStatus::RUNNING)
			{				
				$this->job_status_id = $result ? JobStatus::SUCCESS : JobStatus::ERROR;
			}
			
			$this->onSuccess();
		}
		catch (Exception $e)
		{
			$this->job_status_id = JobStatus::EXCEPTION;
			$this->_finishMessage = array(
				'job_data' => $this->job_data,
				'message' => $e->getMessage(),
				'trace' => $e->getTrace()
			); 	

			$this->_onError();
		}
		
		//in case the job has left a transaction, we rollback here!
		if ($this->rollbackOpenTransaction)
		{
			if ($transaction = Yii::app()->db->getCurrentTransaction())
				$transaction->rollback();
		}

		$this->afterExecute();
	}
	
	protected function logResult()
	{
		Yii::trace("log result", self::$logClass);
		$log = new JobLog();
		$log->job_class = $this->job_class;
		$log->start_time = $this->start_time;
		$log->job_status_id = $this->job_status_id;
		$log->finish_time = $this->timestampToDatabaseDate();
		$log->finish_message = $this->getFinishMessage();
		$log->create_time = $this->timestampToDatabaseDate();
		$log->queue = $this->queue;
		$log->progress = $this->progress;
		$log->job_data = $this->job_data;
		$log->job_id = $this->id;
		$log->token = $this->token;
        $log->identifier1 = $this->identifier1;
        $log->identifier2 = $this->identifier2;
        $log->identifier3 = $this->identifier3;
        $log->identifier4 = $this->identifier4;

		if (!$log->save())
			return Yii::log('Saving a job log failed: ' . var_export($log->errors, true), 'error');

		if ($log->job_status_id == JobStatus::EXCEPTION)
			Yii::log("Running job {$log->job_class} resulted in an exception: " . print_r($this->extractErrorMessageFromFinishMessage($log), true) . ". Please see log ID {$log->id} for details", 'error');

		if ($log->job_status_id == JobStatus::ERROR)
			Yii::log("Running job {$log->job_class} resulted in an error: " . print_r($this->extractErrorMessageFromFinishMessage($log), true) . " Please see log ID {$log->id} for details", 'error');

	}

	/**
	 * @param JobLog $log
	 * @return string
	 */
	private function extractErrorMessageFromFinishMessage(JobLog $log)
	{
		$finish = $log->finish_message;
		if (is_string($finish))
			$finish = json_decode($finish, true);

		if (isset($finish['error']))
			return $finish['error'];

		if (isset($finish['message']))
			return $finish['message'];

		return '';
	}
	
	public function afterExecute()
	{
		Yii::trace("after execute, jobStatus = {$this->job_status_id}", self::$logClass);
		if ($this->job_status_id == JobStatus::SUCCESS)
			$this->endProgress();

		if ($this->persist)
		{
			$this->logResult();

			$this->planned_time = null;
			$this->calculateNextPlannedTime();

			if ($this->crontab)
			{
				$this->calculateNextPlannedTime();
				$this->start_time = null;
				$this->job_status_id = JobStatus::ENQUEUED;
				$this->initializeProgress();
				$this->refreshToken();
				$this->save();
			}
			else
			{
				//Yii::trace("job deleted");
				$this->delete();
			}
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

		//Yii::trace("class ".get_class($this)."setting array: ".print_r($data, true));
		
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