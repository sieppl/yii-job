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
	
	public function actionRunJobs()
	{
		Yii::app()->jobManager->runJobs();
	}
	
	public function actionIndex()
	{
		Yii::app()->jobManager->removeHangingJobs();
		Yii::app()->jobManager->syncJobs();
		Yii::app()->jobManager->runJobs(); 
	}
}