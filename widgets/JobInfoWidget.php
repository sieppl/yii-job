<?php

/**
 * Depends von extensions/bootstrap
 * @see TbProgress
 * @author sieppl
 *
 */
class JobInfoWidget extends CWidget
{
	/**
	 * 
	 * @var JobInterface
	 */
	public $model;
	
	public $progressOptions = array("htmlOptions" => array("class" => "job-progress"));
	public $statusOptions = array("class" => "job-status");
	
	public $onComplete;
	public $completeUrl;
	
	public $enableRunNow = false;
	public $enableStatusText = true;
	
	protected $doRefresh = false;
	
	public $beforeHtml;
	
	public static function registerCS()
	{
		$script = "$(document).yiijob();";
		Yii::app()->clientScript->registerScript("yiijob", $script, CClientScript::POS_READY);		
	}
	
	public function init()
	{
		if (!$this->model)
			return;
		
		// Set visibility
		if (in_array($this->model->jobStatusId, array(JobStatus::ENQUEUED, JobStatus::RUNNING)))
		{
			$this->doRefresh = true;
		}
		
		// Script
		$options = array(
				"onComplete" => $this->onComplete,
				'completeUrl' => $this->completeUrl
				);
		$jsOptions = CJavaScript::encode($options);
		
		$script = "$('#{$this->id}').yiijob({$jsOptions});";
		Yii::app()->clientScript->registerScript($this->id, $script, CClientScript::POS_READY);
	}
	
	public function run()
	{
		if (!$this->model)
			return;

		$url = $this->controller->createUrl(Yii::app()->jobManager->jobActionRoute."/jobInfo");
		
		echo CHtml::openTag("div", array("id" => $this->id,
				"class" => "job-info",
				"data-jobid" => $this->model->jobId,
				"data-jobToken" => $this->model->jobToken,
				"data-statusid" => $this->model->jobStatusId,
				"data-statusName" => JobStatus::getStatusName($this->model->jobStatusId),
				"data-refresh" => $this->doRefresh,
				"data-progress" => $this->model->jobProgress,
				"data-href" => $url));
		
		echo $this->beforeHtml;
		$this->renderProgress();
		$this->renderStatus();
				
		echo CHtml::closeTag("div");
	}
	
	public function renderProgress()
	{
		$progressOptions = $this->progressOptions;
		$progressOptions["htmlOptions"]["style"] = "display:none;";
		
		$this->widget("TbProgress", $progressOptions);		
	}
	
	public function renderStatus()
	{
		$statusOptions = $this->statusOptions;
		$statusOptions["style"] = "display:none;";
		
		echo CHtml::openTag("div", $statusOptions);
		
		if ($this->enableStatusText)
		{
			echo CHtml::tag("span", array("class" => "job-status-text", "style" => "display:none;"), "");
		}
				
		if ($this->enableRunNow)
		{
			$url = $this->controller->createUrl(Yii::app()->jobManager->jobActionRoute."/jobControl", array("action" => "start"));
			echo CHtml::button("Run now", array("class" => "btn btn-primary btn-mini job-status-runnow", "data-loading-text" => "Starting...", "style" => "display:none;", "data-href" => $url));
		}
		
		echo CHtml::closeTag("div");
	}
}