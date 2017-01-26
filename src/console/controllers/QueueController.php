<?php namespace edwardstock\queue\console\controllers;

declare(ticks=1);
use edwardstock\forker\handler\CallbackTask;
use edwardstock\forker\log\Logger;
use edwardstock\forker\ProcessManager;
use edwardstock\forker\system\PIDManager;
use edwardstock\queue\config\ServiceConfig;
use edwardstock\queue\drivers\rabbitmq\RabbitPayload;
use edwardstock\queue\impl\yii\QueueComponent;
use edwardstock\queue\Queue;
use yii\console\Controller;


/**
 * Queue Process Command
 *
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class QueueController extends Controller
{

    /**
     * @var string Driver name
     */
    public $driver;

    /**
     * @var bool Debug mode
     */
    public $debug = true;

    /**
     * @var string Config file path
     */
    public $config = '/etc/yii2queue/queue.conf';

    /**
     * @var ServiceConfig
     */
    private $serviceConfig = null;

    /**
     * @var ProcessManager
     */
    private $processManager;

    public function init()
    {
        parent::init();
        umask(0);
        chdir('/');
    }

    public function actionListen()
    {
        /** @var Queue $queue */
        $component        = QueueComponent::find();
        $component->debug = $this->debug;
        $component->getLogger()->info("Yii2 queue consumer server started!");
        if ($this->serviceConfig === null) {
            $this->serviceConfig = $this->loadConfig();
        }

        Logger::setLevel(Logger::DEBUG);
        $this->processManager = new ProcessManager();
        $this->processManager->attachSignalCommonHandler(function ($sig) {
            echo "Signal: {$sig}\n";
        });

        $this->listen($component, $this->serviceConfig->getAll());
    }

    public function actionPush()
    {
        $q = \Yii::$app->queue;

//        $q->push(new RedisPayload(TestJob::class, 'queue:sum', ['some'=>'value']));
//        $q->push(new RabbitPayload(new Process('ls -lsa > /tmp/list.test')));
        $q->push(RabbitPayload::create([$this, 'runInQueue'])->setData('val1', 'val2'));
    }

    public function runInQueue($arg1, $arg2)
    {
        fwrite(STDOUT, "Hello from queue {$arg1} {$arg2}!\n");
    }

    public function onDone($result, CallbackTask $task)
    {
        $task->getLogger()->info("Exit status: {$result}");
    }

    public function onError(\Throwable $e, CallbackTask $task)
    {
        $task->getLogger()->err($e);
    }

    public function onReload($signal)
    {
        echo "SIGUSR1 {$signal}\n";
        $this->processManager->stop(SIGTERM);
//        $this->processManager = null;
//        $this->serviceConfig = null;
//        $this->actionListen();
    }

    public function options($actionID)
    {
        $options = [
            'listen' => [
                'config',
                'debug',
            ],
        ];
        $parent  = parent::options($actionID);

        return array_merge($parent, ($options[$actionID] ?? []));
    }

    /**
     * @param Queue $queue
     * @param array $driversConfigs
     */
    private function listen(Queue $queue, array $driversConfigs)
    {
        $queue->disconnectDrivers();
        if ($this->debug) {
            $this->processManager->setLogLevel(Logger::INFO | Logger::WARNING);
        }

        $totalWorkers = 0;

        foreach ($driversConfigs AS $driverName => $params) {
            foreach ($params AS $param) {
                $queueName    = $param['queue'];
                $workers      = $param['workers'];
                $totalWorkers += $workers;

                for ($i = 0; $i < $workers; $i++) {
                    $job = CallbackTask::create(function (
                        CallbackTask $task,
                        Queue $queue,
                        string $driverName,
                        string $queueName
                    ) {
                        cli_set_process_title($driverName . ' yii2 queue[' . $queueName . '] worker process');
                        $status = 0;
                        $driver = $queue->getDriver($driverName);
                        $driver->connect();
                        $driver->prepareMultiThreaded();

                        try {
                            $driver->handleJobs($queueName, !$task->isTerminated());
                        } catch (\Throwable $e) {
                            $queue->getLogger()->error($e);
                            $status = 1;
                        } finally {
                            $driver->disconnect();
                        }

                        return $status;
                    });
                    $job->addArgument($queue, $driverName, $queueName);
                    $job->future([$this, 'onDone']);
                    $job->error([$this, 'onError']);
                    $job->attachSignalsHandler([SIGTERM, SIGINT], function ($signo) {
                        $ph = PIDManager::getInstance(PID_FILE);
                        foreach ($ph->getAll(true) AS $pid) {
                            posix_kill($pid, SIGTERM);
                        }
                    });
                    $this->processManager->add($job);
                }
            }
        }

        $this->processManager->setProcessTitle('yii2 queue: master process [' . $totalWorkers . ' worker(s)]');

        $this->processManager->attachSignalsHandler([SIGTERM, SIGINT], function () {
            $this->processManager->stop();
        });
        $this->processManager->run(true);


        $childs = $this->processManager->getWorkingPids();


        while (($exited = pcntl_wait($status, WNOHANG | WUNTRACED)) != -1) {
            usleep(500000);
            pcntl_signal_dispatch();
        }
    }

    private function loadConfig()
    {
        return ServiceConfig::load($this->config);
    }
}
