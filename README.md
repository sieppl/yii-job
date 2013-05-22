yii-job
=======

A simple module to have cron-like jobs in your database. In addition to yii's commands, yii-job can be used to create on-the-fly asynchronous jobs. 
Different kinds of jobs are supported:

1. Jobs with crontab that are triggered at defined times.
2. Ad-Hoc jobs that are executed at a defined time (or as soon as possible).

To actually process the jobs you can use the JobCommand, which itself can be triggered by a sytem cron job. 
A common scenario is system a cron job that is executed once per minute to trigger JobCommand.

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

Thanks
----------
Thanks to mtdowling for providing a nice php crontab parser: https://github.com/mtdowling/cron-expression
