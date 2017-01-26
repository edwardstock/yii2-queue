Queue component for Yii2
====================
This component provides advanced standalone yii2 queue worker/unix service

## ! In development now !


### Features
* Worker can be a class implements edwardstock\queue\Worker interface with single method: run($job, $data)
```php
<?php
use \edwardstock\queue\Worker;
use \edwardstock\queue\drivers\redis\RedisPayload;

class MySuperWorker implements Worker
{
    public function run($job, $data) 
    {
        // do some $job with $data
    }
}

$queue = \Yii::$app->queue;
$queue->push(new RedisPayload(MySuperWorker::class, 'super_queue', $val1, $val2, ...$vargs));
```
* Worker can be a callback or closure! 
```php
<?php
use \edwardstock\queue\drivers\rabbitmq\RabbitPayload;
$queue = \Yii::$app->queue;

$usefulVariable = 42;
$queue->push(RabbitPayload::create(function() use($usefulVariable) {
    // do some code in background
}));
```
* Worker can be a shell command that will executed with return value
```php
<?php
use \edwardstock\queue\drivers\rabbitmq\RabbitPayload;

$process = new \Symfony\Component\Process\Process('ls -lsa ./ >> /tmp/list_files.txt');

$queue = \Yii::$app->queue;
$queue->push(RabbitPayload::create($process));

```
* Standalone and customizable runner service. 
* Batch jobs push to queues
```php
<?php
use \edwardstock\queue\drivers\rabbitmq\RabbitPayload;
use \edwardstock\queue\drivers\redis\RedisPayload;

$jobs = [];
for($i = 0; $i < 10; $i++) {
    $jobs[] = ($i % 2 === 0) ? 
    new RabbitPayload(SomeJob::class) : 
    new RedisPayload(function(){/*do some*/});
}

$queue = \Yii::$app->queue;
// here all jobs will be pushed not by each one but as much possible by one part
$queue->pushBatch($jobs); 


```
* Storing information about queues in runtime file

### TODOs
* Delayed push via sqlite database storage
* Unique payload pushing
* Test for everything and improve interface for better user-defined payloads