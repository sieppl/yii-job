<?php

interface JobInterface
{
	public function getJobData();
	public function getJobId();
	public function getJobToken();
	public function getJobStatusId();
	public function getJobProgress();
	public function getJobResult();
	
	public function getJobInfo();
}