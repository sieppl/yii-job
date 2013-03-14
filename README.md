yii-job
=======

Installing
----------

In your config add the module:

```php
  'modules' => array(
			'yii-job' => array(
			),
	),
```

In your config add the JobManager component:

```php
  'components'=>array(			
		'jobManager' => array(
				'class' => 'application.modules.yii-job.components.JobManager',
				'jobs' => array(
							array(
								'class' => 'MyJob', //this is your own class that extends application.modules.yii-job.models.Job
								'crontab' => '* 3 * * *'
							)
						)
		),
	),
```

Optional: add module models import for convenicence

```php
  'import'=>array(
		'application.modules.yii-job.models.*'
	),	
```
