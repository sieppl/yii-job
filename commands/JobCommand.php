<?php
class JobCommand extends CConsoleCommand
{
	
	public function actionRemoveHangingJobs()
	{
		Yii::app()->jobManager->removeHangingJobs();
	}
	
	public function actionSyncJobs()
	{
		Yii::app()->jobManager->syncJobs();
	}
	
	public function actionRunJobs($queue = null)
	{
		Yii::app()->jobManager->runJobs($queue);
	}
	
	public function actionIndex($queue = null)
	{
		Yii::app()->jobManager->removeHangingJobs();
		Yii::app()->jobManager->syncJobs();
		Yii::app()->jobManager->runJobs($queue); 
	}
}