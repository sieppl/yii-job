yii-job
=======

Database Installing
----------

Import data/mysql.sql into your database.

Yii Installing
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

In your console config import the JobCommand

```php
'commandMap' => array(
	'job' => 'application.modules.yii-job.commands.JobCommand'		
),
```

Run
----------

If you want to trigger the job processing from the command line you still need a cron job that executes the JobCommand.
It should be triggers like this:

```
yiic job
```

This is the index command which will sync your jobs in the config with your database and run all jobs that are due.
