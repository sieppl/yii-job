<?php
class JobInfosAction extends CAction
{	
	public function run()
	{
		$jobTokens = array();
		if (isset($_GET["jobTokens"]))
		{
			$tmp = $_GET["jobTokens"];
			if (!is_array($tmp))
				$jobTokens = array($tmp);
		}
		
		$result = Yii::app()->jobManager->getJobInfos($jobTokens, true);
		
		echo json_encode($result);
		Yii::app()->end();
	}	
}