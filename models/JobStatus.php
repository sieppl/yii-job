<?php
class JobStatus
{
	const ENQUEUED = 1; //job is waiting for execution
	const RUNNING = 2; //job is currently executed
	const SUCCESS = 3; //job was successfull
	const ERROR = 4; //job had an internal error,  but no exception
	//const ABORTED = 5; 
	const PAUSED = 6; //job is paused and not considered for execution
	const EXCEPTION = 7; //job threw an exception 
	
}