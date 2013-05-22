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
	 *                     'job_data => array( //optional attributes that are passed to the job
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
	
	protected function saveJob(Job $job)
	{
		if (!$job->save())
		{
			Yii::log('Saving a job failed: '.print_r($job->errors), 'error');
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
	
	protected function timestampToDatabaseDate($timestamp = null)
	{
		if ($timestamp === null)
		{
			$timestamp = time();
		}
	
		return date("Y-m-d G:i:s", $timestamp);
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

		Yii::trace("checking for jobs started before $startTime");
		
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
	 * Will sync jobs from config with database. Updates the planned time when the job already exists and the crontab has changed.
	 * 
	 * @throws Exception
	 */
	public function syncJobs()
	{
		$ids = array();
		foreach ($this->jobs as $attributes)
		{
			if (!isset($attributes['class']))
			{
				throw new Exception('Job needs to define a class');
			}
			
			if (!isset($attributes['crontab']))
			{
				throw new Exception('Job needs to define a crontab value');
			}
			
			$model = Job::model()->find('t.job_class=:job_class AND t.crontab IS NOT NULL', array(':job_class' => $attributes['class']));
			if (!$model)
			{
				$model = new Job;
				$model->job_class = $attributes['class'];
			}
			elseif ($model->job_status_id == JobStatus::RUNNING)
			{
				$ids[] = $model->id;
				//do not sync a job which is currently running, we do not want to break it
				continue;	
			}

			$oldCrontab = $model->crontab;		
			
			unset($attributes['class']);
			
			$model->setAttributes($attributes);
			
			if ($attributes['crontab'] != $oldCrontab)
				$model->calculateNextPlannedTime();
			
			$this->addJob($model, false);
			
			$ids[] = $model->id;
		}
		
		//delete all jobs with crontab entry that were not modified during the loop before
		$criteria = new CDbCriteria();
		$criteria->addCondition('crontab IS NOT NULL');
		$criteria->addNotInCondition('id', $ids);
		Job::model()->deleteAll($criteria);
	}
	
	public function runJobs()
	{
		Yii::trace("Run Jobs");
		$now = $this->timestampToDatabaseDate();
		$job = Job::model()->find(array(
				 'condition' => 'planned_time <= :planned_time AND job_status_id=:job_status_id',
				 'order' => 'planned_time ASC',
				 'limit' => 1,
				 'params' => array(':planned_time' => $now, ':job_status_id' => JobStatus::ENQUEUED)
				));

		if ($job)
		{
			Yii::trace("execute job {$job->id}");
			$job->execute();
			$this->runJobs();
		}
	}
		
}