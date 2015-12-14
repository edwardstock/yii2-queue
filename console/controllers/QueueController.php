<?php

namespace atlas\queue\console\controllers;

use atlas\queue\Job;
use atlas\queue\Queue;
use Yii;
use yii\console\Controller;

/**
 * Queue Process Command
 *
 * Class QueueController
 * @package wh\queue\console\controllers
 */
class QueueController extends Controller
{
    private $sleep = 0;

    /**
     * Process a job
     *
     * @param string $queueName
     * @param string $queueObjectName
     * @throws \Exception
     */
    public function actionWork($queueName = null, $queueObjectName = 'queue')
    {
        $this->process($queueName, $queueObjectName);
    }

	/**
	 * Continuously process jobs
	 *
	 * @param string $queueName
	 * @param string $queueObjectName
	 * @param int $sleep
	 * @return bool
	 */
    public function actionListen($queueName = null, $queueObjectName = 'queue', $sleep = 0)
    {
	    $this->sleep = $sleep;

        while (true) {
            if (!$this->process($queueName, $queueObjectName)) {
	            if($this->sleep > 0) sleep($this->sleep);
            }

        }
    }

    protected function process($queueName, $queueObjectName)
    {
	    /** @var Queue $queue */
        $queue = Yii::$app->{$queueObjectName};
	    /** @var Job $job */
        $job = $queue->pop($queueName);

        if ($job) {
            try {
                $job->run();
                return true;
            } catch (\Exception $e) {
                if ($queue->debug) {
                    var_dump($e);
                }

                Yii::error($e->getTraceAsString(), __METHOD__);
            }
        }
        return false;
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (getenv('QUEUE_SLEEP')) {
            $this->sleep=(int)getenv('QUEUE_SLEEP');
        }
        return true;
    }
}
