<?php

class JobInfo
{
	public $progress;
	public $statusId;
	public $result;
	
	public function __construct($job)
	{
		$this->progress = $job->jobProgress;
		$this->statusId = $job->jobStatusId;
		$this->result = $job->jobResult;
		$this->statusName = JobStatus::getStatusName($job->jobStatusId);
	}
	
	public function toArray()
	{
		$data["progress"] = $this->progress;
		$data["statusId"] = $this->statusId;
		$data["statusName"] = $this->statusName;
		$data["result"] = $this->result;
		
		return $data;
	}
	
	public function toJson()
	{
		return json_encode($this->toArray());
	}
}