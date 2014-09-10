<?php
class JobLastNextWidget extends CWidget
{
	public $filterAttributes;
	
	/**
	 * 
	 * @var Job
	 */
	private $nextJob;
	
	/**
	 * 
	 * @var JobLog
	 */
	private $lastJobLog;
	
	public $jobManagerComponentName = 'jobManager';
	public $gridClass = 'CGridView';
	
	
	public function init()
	{
		parent::init();
		
		$this->id = md5(implode("", $this->filterAttributes));
		
		$jobManagerComponentName = $this->jobManagerComponentName;
		$this->nextJob = Yii::app()->$jobManagerComponentName->findJob($this->filterAttributes);
		$this->lastJobLog = Yii::app()->$jobManagerComponentName->findJobLog($this->filterAttributes, array('order' => 'finish_time DESC'));
	}
	
	public function run()
	{
		$gridId = $this->id;
		
		Yii::app()->controller->widget($this->gridClass, array(
				'id' => $this->id,
				'dataProvider'=> new CArrayDataProvider(array($this->lastJobLog, $this->nextJob), array('pagination' => false)),
				'template' => "{items}",
				'afterAjaxUpdate' => "js:function() {
					$('#job').yiijob({'onComplete':function() {
						$.fn.yiiGridView.update('{$gridId}');
					}});
					
					$('#jobLog').yiijob({'onComplete':function() {
						$.fn.yiiGridView.update('{$gridId}');
					}});
				}",
				'columns' => array(
						array(
								'name' => '',
								'type' => 'raw',
								'value' => '$row == 0 ? "Last run" : "Next run"',
								'htmlOptions' => array('style' => 'width: 60px;'),
						),
						array(
								'name' => '',
								'type' => 'raw',
								'value' => '$row == 0 ? ($data ? $data->finish_time : "None") : ($data ? $data->planned_time : "None")',
								'htmlOptions' => array('style' => 'width: 140px;'),
						),
						array(
								'name' => '',
								'type' => 'raw',
	   			 			'value' => function($data, $row) use ($gridId) {

   			 						Yii::app()->controller->widget("application.modules.yii-job.widgets.JobInfoWidget",
   			 								array(
   			 										"model" => $data,
   			 										"enableRunNow" => true,
   			 										"id" => ($row == 0 ? "jobLog" : "job"),
   			 										"onComplete" => "js:function(status) {
   			 											$.fn.yiiGridView.update('{$gridId}');
   			 										}"
   			 								)
   			 						);
   							}
						),
				),
		));
	}
}