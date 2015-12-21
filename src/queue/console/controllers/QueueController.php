<?php namespace atlasmobile\queue\console\controllers;

use atlasmobile\models\FailedJobs;
use atlasmobile\queue\BaseQueue;
use atlasmobile\queue\Job;
use Yii;
use yii\base\Exception;
use yii\console\Controller;

/**
 * Queue Process Command
 *
 * Class QueueController
 *
 * atlas. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 * @package atlasmobile\queue\console\controllers
 */
class QueueController extends Controller
{
	public static $TAG = __CLASS__;
	public $sleep = 1;
	public $tries = 3;
	public $queueName = 'default';
	public $queueObjectName = 'queue';
	public $storeFailedJobs = false;

	public function beforeAction($action) {
		if ($this->storeFailedJobs === 'true' || $this->storeFailedJobs === '1') {
			$this->storeFailedJobs = true;
		}
		return parent::beforeAction($action); // TODO: Change the autogenerated stub
	}

	/**
	 * Process a job
	 *
	 * @throws \Exception
	 */
	public function actionWork() {
		$this->process();
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function process() {
		/** @var BaseQueue $queue */
		$queue = $this->getQueue();
		/** @var Job $job */
		$job = $queue->pop($this->queueName);

		if ($job instanceof Job) {
			try {
				$job->run();
				return true;
			} catch (\Exception $e) {
				Yii::error($e->getTraceAsString(), self::$TAG);
				$payload = $job->getPayload();

				if ($payload->getParams() === null) {
					$payload->setParams([]);
				}

				if ($payload->hasParam('tries') && $payload->getParam('tries', 0) < $this->tries) {
					$payload->setParam('tries', $payload->getParam('tries', 0) + 1);
					Yii::info("Tries " . $payload->getParam('tries', 0) . PHP_EOL, self::$TAG);
				} else if (!$payload->hasParam('tries')) {
					$payload->setParam('tries', 1);;
					Yii::info("Tries " . $payload->getParam('tries', 0) . PHP_EOL, self::$TAG);
				} else {
					Yii::error("TRY #" . $payload->getParam('tries') . ': ' . $e->getTraceAsString(), self::$TAG);
					if ($this->storeFailedJobs) {
						$this->storeFailed($payload->getClass(), $payload->getParam('tries'), $payload);
					}
					throw $e;
				}
				$queue->push($payload->getClass(), $payload->getParams(), $job->getQueueName());

				throw $e;
			}
		}
		return false;
	}

	/**
	 * @return BaseQueue
	 */
	private function getQueue() {
		return Yii::$app->{$this->queueObjectName};
	}

	/**
	 * @param string $className
	 * @param int $tries
	 * @param string $payload
	 * @throws \yii\db\Exception
	 */
	private function storeFailed($className, $tries, $payload) {
		try {
			FailedJobs::add($className, $tries, $payload);
		} catch (Exception $ex) {
			throw new \yii\db\Exception('Table failed_jobs not created. Please, run: queue/table-failed');
		}
	}

	/**
	 * Continuously process jobs
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function actionListen() {
		while (true) {
			$cmd = $this->buildWorkerCommand();
			echo exec($cmd, $out);
			echo implode(PHP_EOL, $out);
			sleep($this->sleep);
		}
	}

	/**
	 * @param string $actionName
	 * @return string
	 */
	private function buildWorkerCommand($actionName = 'work') {
		$cmd = [];
		$cmd[] = PHP_BINARY;
		$cmd[] = Yii::$app->basePath . '/../yii queue/' . $actionName;
		$params = [];
		foreach ($this->options(null) AS $option) {
			if (in_array($option, parent::options(null))) continue;
			$params['--' . $option] = $this->$option;
		}

		$out = implode(' ', $cmd) . ' ';
		foreach ($params AS $k => $v) {
			$out .= $k . '=' . $v;
			$out .= ' ';
		}

		return $out;
	}

	public function options($actionID) {
		return array_merge(parent::options($actionID), [
			'tries', 'sleep', 'queueName', 'queueObjectName', 'storeFailedJobs'
		]);
	}

	/**
	 * Creates table with failed jobs
	 */
	public function actionTableFailed() {
		$this->run('migrate/up', [
			'migrationPath' => '@vendor/atlasmobile/yii2-queue/src/migrations'
		]);
	}

	/**
	 * Handle failed jobs. Adds they to queue.
	 */
	public function actionFailed() {
		/** @var FailedJobs $item */
		foreach (FailedJobs::find()->all() AS $item) {
			$payload = unserialize($item->payload);
			$payload->data->tries = 0;

			$this->getQueue()->push($item->class, $payload->data, $this->queueName);
			$item->delete();
		}
	}
}
