<?php
class JobControlAction extends CAction
{	
	public function run($token, $action = "start")
	{		
		$res = array();
	
		try
		{
			$job = Job::model()->findByAttributes(array("token" => $token));
		
			if (!$job)
				throw new Exception("Unable to find job {$token}");

			switch ($action)
			{
				case "start":
					$job->startNow();
					break;
			}
			
			if (!$job->save())
				throw new Exception("Unable to save job {$token}");
			
			$res["result"] = "success";
		}
		catch (Exception $ex)
		{
			$res["result"] = "error";
			$res["error"] = $ex->getMessage();
		}
		
		echo json_encode($res);
	}	
}