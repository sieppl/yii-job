<?php
class JobInfoAction extends CAction
{	
	public function run($token)
	{		
		$result = Yii::app()->jobManager->getJobInfo($token, true);
		
		echo json_encode($result);
		Yii::app()->end();
	}	
}