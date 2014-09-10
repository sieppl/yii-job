<?php
class JobStatus
{
	const ENQUEUED = 1; //job is waiting for execution
	const RUNNING = 2; //job is currently executed
	const SUCCESS = 3; //job was successfull
	const ERROR = 4; //job had an internal error, but no exception
	//const ABORTED = 5; 
	const PAUSED = 6; //job is paused and not considered for execution
	const EXCEPTION = 7; //job threw an exception 
	
	public static $types = array(
			self::ENQUEUED => array('name' => 'Enqueued'),
			self::RUNNING => array('name' => 'Running'),
			self::SUCCESS => array('name' => 'Success'),
			self::ERROR => array('name' => 'Error'),
			self::PAUSED => array('name' => 'Paused'),
			self::EXCEPTION => array('name' => 'Exception'),
	);
	
	public static function getStatus($statusId)
	{
		if (array_key_exists($statusId, self::$types))
		{
			return self::$types[$statusId];
		}
	}
	
	public static function getStatusName($statusId)
	{
		$stat = self::getStatus($statusId);
		if ($stat)
			return $stat["name"];
		
		return null;
	}
	
}