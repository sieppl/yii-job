<?php
class JobManager extends CApplicationComponent
{
	/**
	 * A list of job types that have an array describing the job. 
	 * These jobs are synched with the database. All recurring jobs in the database are cleaned up, when they are not mentioned in the config.
	 * 
	 * @var array
	 * 
	 * Jobs may be configured in application configuration like following:
	 * <pre>
	 * array(     
	 *     'components'=>array(
	 *         'jobManager'=>array(
	 *             'class'=>'JobManager',
	 *             'jobs'=>array(
	 *                 'nightlyMail' => array(
	 *                     'class'=>'JobSendNighlyMails',
	 *                     'crontab' => '* 3 * * *', //optional, defines a recurring job in crontab style
	 *                     'job_data' => array( //optional attributes that are passed to the job
	 *                 			'foo' => 'bar' 
	 *                     ),
	 *                 ),
	 *             ),
	 *         ),
	 *     ),
	 * )
	 * </pre>
	 */
	public $jobs = array();
	
	//The time after start_time running jobs are removed from the queue
	public $removeHangingJobsThreshold = 28800; //8 hours
	
	public $jobActionRoute = null;
	
	public $clearComponents = array();
	
	public $exitAfterRun = false;

	protected static $logClass = "ext.yii-job.components.JobManager";
	
	protected function saveJob(Job $job)
	{
		if (!$job->save())
		{
			Yii::log('Saving a job failed: '.print_r($job->errors, true), 'error');
			return false;
		}
		return true;
	}
	/**
	 * 
	 * @param Job $job
	 */
	public function addJob(Job $job, $checkEnqueuedDuplicate = true)
	{			
		if ($checkEnqueuedDuplicate)
		{
			$duplicate = $this->findEnqueuedDuplicateJob($job);
			if ($duplicate)
			{
				$duplicate->planned_time = $job->planned_time;
				return $this->saveJob($duplicate);
			}		
		}
		
		return $this->saveJob($job);
	}

    public function execJob($job)
    {
	    Yii::trace("Exec job directly, class = ".get_class($job));
        $ret = $job->executeDirect();
	    if ($ret != JobStatus::SUCCESS)
		    throw new Exception("Job execution was not successful: ".print_r($job->getFinishMessage(), true));
    }
	
	protected function timestampToDatabaseDate($timestamp = null)
	{
		if ($timestamp === null)
		{
			$timestamp = time();
		}
	
		return date("Y-m-d G:i:s", $timestamp);
	}	
	
	private function clearComponents()
	{
		foreach ($this->clearComponents as $id)
		{
			Yii::trace("Clear component: $id");
			Yii::app()->setComponent($id, null);
		}
	}
	
	/**
	 * 
	 * @param Job $job
	 * @return Job $job
	 */
	protected function findEnqueuedDuplicateJob(Job $job)
	{
		$criteria = new CDbCriteria(array(
				'condition' => 'crontab IS NULL AND job_class=:job_class AND job_status_id=:job_status_id',
				'params' => array(':job_class' => $job->job_class, ':job_status_id' => JobStatus::ENQUEUED),
				));
		$jobs = Job::model()->findAll($criteria);
		foreach ($jobs as $compareJob)
		{
			if ($compareJob->isDuplicateOf($job))
				return $compareJob;
		}
		return null;
	}
	
	/**
	 * checks the database for running jobs older than the treshold in {@link removeHangingJobsThreshold}, logs an error and removes them from queue
	 */
	public function removeHangingJobs()
	{
		$startTime = $this->timestampToDatabaseDate(strtotime("- {$this->removeHangingJobsThreshold} seconds")); 

		//Yii::trace("checking for jobs started before $startTime");
		
		$criteria = new CDbCriteria(array(
				'condition' => 'start_time < :start_time AND job_status_id=:job_status_id',
				'params' => array(':start_time' => $startTime, ':job_status_id' => JobStatus::RUNNING),
		));
		
		$jobs = Job::model()->findAll($criteria);
		foreach ($jobs as $job)
		{
			$job->abort();
		}
	}
	
	/**
	 * 
	 * @param mixed $attributes
	 * @return Job
	 * @throws Exception
	 */
	public function createJobFromArray($attributes)
	{
		if (!isset($attributes['class']))
		{
			throw new Exception('Job needs to define a class');
		}
		
		$class = $attributes['class'];
		$jobData = array();
		unset($attributes['class']);
		
		if (isset($attributes['job_data']))
		{
			$jobData = $attributes['job_data'];
			unset($attributes['job_data']);
		}
		
		
		$model = new $class;
		$model->job_class = $class;
		$model->setAttributes($attributes);
		$model->setJobData($jobData);
		
		return $model;
	}
	
	/**
	 * 
	 * @param mixed $attributes
	 * @return Job
	 * @throws Exception
	 */
	public function addCronTabJobFromArray($attributes)
	{
		if (!isset($attributes['class']))
		{
			throw new Exception('Job needs to define a class');
		}
		
		if (!isset($attributes['crontab']))
		{
			throw new Exception('Job needs to define a crontab value');
		}
		
		$model = $this->createJobFromArray($attributes);
		
		$models = Job::model()->findAll('t.job_class=:job_class AND t.crontab IS NOT NULL', array(':job_class' => $attributes['class']));
				
		foreach ($models as $compareModel)
		{
			if ($model->isDuplicateOf($compareModel))
			{
				$model = $compareModel;
				break;
			}	
		}
		
		if (isset($attributes['job_origin_id']))
		{
			$model->job_origin_id = $attributes['job_origin_id'];
		}

		if ($model->job_status_id == JobStatus::RUNNING)
		{
			//do not sync a job which is currently running, we do not want to break it
			return $model;
		}		
		
		if ($attributes['crontab'] != $model->crontab)
		{
			Yii::trace("Setting new time");
			$model->calculateNextPlannedTime();
		}
		
		$this->addJob($model, false);
		
		return $model;
	}
	
	public function addCronTabJobFromArrayAndRunOnce($attributes)
	{
		if (!isset($attributes['class']))
		{
			throw new Exception('Job needs to define a class');
		}
	
		if (!isset($attributes['crontab']))
		{
			throw new Exception('Job needs to define a crontab value');
		}
	
		$model = $this->createJobFromArray($attributes);
	
		$models = Job::model()->findAll('t.job_class=:job_class', array(':job_class' => $attributes['class']));
	
		foreach ($models as $compareModel)
		{
			if ($model->isDuplicateOf($compareModel))
			{
				//the requested job is already enqueued or running
				return $compareModel;
			}
		}
		
		$model->calculateNextPlannedTime();
		$model->crontab = null;
		$this->addJob($model, false);
	
		return $model;
	}
	
	/**
	 * Will sync jobs from config with database. Updates the planned time when the job already exists and the crontab has changed.
	 * 
	 * @throws Exception
	 */
	public function syncJobs()
	{
		$ids = array();
		foreach ($this->jobs as $attributes)
		{
			//Yii::trace("Adding job: ".print_r($attributes, true));
			$attributes['job_origin_id'] = JobOrigin::CONFIG;			
			$model = $this->addCronTabJobFromArray($attributes);			
			$ids[] = $model->id;
		}
		
		//delete all jobs with crontab entry that were not modified during the loop before
		$criteria = new CDbCriteria();
		$criteria->addCondition('job_origin_id = '.JobOrigin::CONFIG);
		$criteria->addNotInCondition('id', $ids);
		Job::model()->deleteAll($criteria);
	}
	
	public function runJobs($queue = null)
	{
		$this->checkRun($queue);
		
		//Yii::trace("Run Jobs");
		$this->onRun(new CEvent($this, array("queue" => $queue)));
		
		$tx = Yii::app()->db->beginTransaction();
		$job = null;
		try
		{
			$now = $this->timestampToDatabaseDate();
			$jobStatus = JobStatus::ENQUEUED;
			
			$sql = "SELECT * FROM job WHERE planned_time <= '{$now}' AND job_status_id = {$jobStatus}";
			if ($queue)
				$sql .= " AND queue = '{$queue}'";
			$sql.= " LIMIT 1 FOR UPDATE";
			
			$job = Job::model()->findBySql($sql);
	
			if ($job)
			{
				$this->onBeforeExecute(new CEvent($this, array("job" => $job, "queue" => $queue)));
				$job->beforeExecute();
			}
			$tx->commit();
		}
		catch (Exception $ex)
		{
			$tx->rollback();
			Yii::log("Error in finding job: ".$ex->getMessage());
		}
		
		if ($job)
		{
			$job->execute();
			$this->onAfterExecute(new CEvent($this, array("job" => $job, "queue" => $queue)));
			
			if ($this->exitAfterRun)
				Yii::app()->end();
			
			$this->runJobs($queue);
		}
	}
	
	/**
	 * Checks if a "stop" file exists for this queue. If a stop files exists, then processing of further jobs is suspended and a "stopped" file is created
	 * 
	 * @param string $queue
	 * @return boolean
	 */
	protected function checkRun($queue)
	{
		$baseFileName = $queue ? $queue : "default";
		
		$stopFilePath = Yii::getPathOfAlias("application.runtime")."/jobs/{$baseFileName}_stop";
		$stoppedFilePath = Yii::getPathOfAlias("application.runtime")."/jobs/{$baseFileName}_stopped";
		
		if (file_exists($stopFilePath))
		{
			touch($stoppedFilePath);
			throw new Exception("Stop file exists");
		}
		else
		{
			if (file_exists($stoppedFilePath))
			{
				unlink($stoppedFilePath);
			}
		}
	}
	
	public function onRun($event)
	{
		$this->raiseEvent('onRun', $event);
	}
	
	public function onBeforeExecute($event)
	{
		$this->raiseEvent('onBeforeExecute', $event);
	}
	
	public function onAfterExecute($event)
	{
		$this->raiseEvent('onAfterExecute', $event);
	}
	
	protected function createEntry($job, $associativeArray = false)
	{
		$info = $job->getJobInfo();
		
		if ($associativeArray)
			return $info->toArray();
		else
			return $info;
	}
		
	/**
	 * 
	 * @param mixed $jobIds
	 */
	public function getJobInfos($tokens = null, $associativeArray = false)
	{
		if (!is_array($tokens))
			$tokens = array($tokens);
		
		//Yii::trace(print_r($jobIds, true));
		
		$result = array();
		
		if (count($tokens))
		{
			// First check for "alive jobs"
			$jobs = Job::model()->findAllByAttributes(array('token' => $tokens));
			
			foreach ($jobs as $job)
			{
				$result[$job->token] = $this->createEntry($job, $associativeArray);
			}
			
			//Yii::trace(print_r(array_keys($result), true));
	
			// Second step is to check for completed jobs in JobLog table
			$delta = array_diff($tokens, array_keys($result));
			
			//Yii::trace("Delta = ".print_r($delta, true));
			
			if (count($delta))
			{
				$crit = new CDbCriteria();
				$crit->addInCondition("token", $delta);
				
				$jobsCompleted = JobLog::model()->findAll($crit);
				
				foreach ($jobsCompleted as $job)
				{
					$result[$job->token] = $this->createEntry($job, $associativeArray);
				}
			}
		}
		
		return $result;
	}
	
	public function getJobInfo($token, $associativeArray = false)
	{
		$ret = null;
		$infos = $this->getJobInfos($token, $associativeArray);
		
		if (array_key_exists($token, $infos))
			$ret = $infos[$token];
		
		return $ret;
	}

    private function prepareFindAttributes($class, $attributes)
    {
        $filterClass = $class;
        if (isset($attributes['job_class']))
            $filterClass = $attributes['job_class'];

        if (isset($attributes['filterClass']))
        {
            $filterClass = $attributes['filterClass'];
            $attributes['job_class'] = $filterClass;
            unset($attributes['filterClass']);
        }

	    if (is_array($filterClass))
		    $filterClass = $filterClass[0];

        $model = $class::model();

        /* @var $filterModel Job */
        $filterModel = $filterClass::model();

        $identifierAttributes = array();
        foreach ($attributes as $name => $value)
        {
            if (!$model->hasAttribute($name))
            {
                $identifiers = array_flip($filterModel->identifiers());
                if (isset($identifiers[$name]))
                {
                    unset($attributes[$name]);
                    $column = $identifiers[$name];
                    $identifierAttributes[$column] = $value;
                }
                else
                    throw new Exception("Attribute {$name} not defined on ($filterClass} or mapped as identifier, defined identifiers: ".print_r($identifiers, true));
            }
        }
        return array_merge($attributes, $identifierAttributes);
    }
	
	private function findAll($class, $attributes, $condition = null)
	{
        $attributes = $this->prepareFindAttributes($class, $attributes);
        $model = $class::model();
		return $model->findAllByAttributes($attributes, $condition);
	}
	
	private function find($class, $attributes, $condition = null)
	{
        $attributes = $this->prepareFindAttributes($class, $attributes);
        $model = $class::model();
        return $model->findByAttributes($attributes, $condition);
	}
	
	public function findAllJob($attributes = array(), $condition = null)
	{
		return $this->findAll('Job', $attributes, $condition);
	}
	
	public function findAllJobLog($attributes = array(), $condition = null)
	{
		return $this->findAll('JobLog', $attributes, $condition);
	}
	
	public function findJob($attributes = array(), $condition = null)
	{
		return $this->find('Job', $attributes, $condition);
	}
	
	public function findJobLog($attributes = array(), $condition = null)
	{
		return $this->find('JobLog', $attributes, $condition);
	}

	public function getJobLogDataProvider($attributes = array())
	{
		$dataProvider = new CActiveDataProvider('JobLog');
		$attributes = $this->prepareFindAttributes('JobLog', $attributes);
		foreach ($attributes as $attribute => $value)
		{
			if (is_array($value))
				$dataProvider->criteria->addInCondition($attribute, $value);
			else
				$dataProvider->criteria->addSearchCondition($attribute, $value);
		}
		return $dataProvider;
	}
}