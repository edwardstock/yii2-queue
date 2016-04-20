<?php namespace atlasmobile\queue\console\controllers;

declare(ticks = 1);

use atlasmobile\queue\BaseQueue;
use atlasmobile\queue\Job;
use atlasmobile\queue\models\FailedJobs;
use atlasmobile\queue\QueuePayload;
use Yii;
use yii\base\Exception;
use yii\console\Controller;
use yii\helpers\Console;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\redis\Connection;

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
	public $loggingJobs = false;

	private $working = true;

	public function init() {
		pcntl_signal(SIGTERM, [$this, 'signalHandler']);
		pcntl_signal_dispatch();
		parent::init();
	}

	public function beforeAction($action) {
		if ($this->storeFailedJobs === 'true' || $this->storeFailedJobs === '1') {
			$this->storeFailedJobs = true;
		}

		if ($this->loggingJobs === 'true' || $this->loggingJobs === '1') {
			$this->loggingJobs = true;
		}
		return parent::beforeAction($action);
	}

	/**
	 * Handle failed jobs. Adds they to queue.
	 */
	public function actionFailed() {
		/** @var FailedJobs $item */
		foreach (FailedJobs::find()->orderBy('id ASC')->all() AS $item) {
			/** @var QueuePayload $payload */
			$payload = unserialize(base64_decode($item->payload));
			$payload->setParam('tries', 0);

			$this->getQueue()->push($item->class, $payload->getParams(), $this->queueName);
			$item->delete();
		}
	}

	/**
	 * @return BaseQueue
	 */
	private function getQueue()
	{
		return Yii::$app->{$this->queueObjectName};
	}

	/**
	 * Flush all failed jobs
	 */
	public function actionFailedFlush() {
		$this->stdout("Flushing failed jobs:\t", Console::FG_GREEN);
		FailedJobs::truncate();
		$this->stdout("[");
		$this->stdout('OK', Console::FG_YELLOW | Console::BOLD);
		$this->stdout("]\n");
	}

	/**
	 * Creates table with failed jobs
	 */
	public function actionFailedTable() {
		$this->run('migrate/up', [
			'migrationPath' => '@vendor/atlasmobile/yii2-queue/src/queue/migrations'
		]);
	}

	/**
	 * Continuously process jobs
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function actionListen() {
		while ($this->working) {
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
	private function buildWorkerCommand($actionName = 'work')
	{
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

	public function options($actionID)
	{
		return array_merge(parent::options($actionID), [
			'tries', 'sleep', 'queueName', 'queueObjectName', 'storeFailedJobs'
		]);
	}

	public function actionTail($lastRowsCount = 10) {

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
				if ((int)$this->tries === 0) {
					throw $e;
				}
				
				$payload = $job->getPayload();

				if ($payload->getParams() === null) {
					$payload->setParams([]);
				}

				$workedTries = $payload->getParam('tries', 0);
				if (!$payload->hasParam('tries')) {
					$payload->setParam('tries', 0);
				}

				if ($workedTries < $this->tries) {
					Yii::info("Error executing job. \nData: " . VarDumper::export($payload->getParams()) . "\nTRY #" . $workedTries . PHP_EOL, self::$TAG);
					$queue->push($payload->getClass(), $payload->getParams(), $job->getQueueName());
					if ($this->storeFailedJobs) {
						$this->storeFailed($payload->getClass(), $payload->getParam('tries'), $payload, $e);
					}
				} else {
					throw $e;
				}

				throw $e;
			}
		}
		return false;
	}

	/**
	 * @param string $className
	 * @param int $tries
	 * @param QueuePayload $payload
	 * @param \Exception $exception
	 * @throws \yii\db\Exception
	 */
	private function storeFailed($className, $tries, QueuePayload $payload, \Exception $exception) {
		try {
			FailedJobs::add($className, $tries, $payload, $exception);
		} catch (Exception $ex) {
			throw new \yii\db\Exception('Table failed_jobs not created. Please, run: queue/table-failed');
		}
	}

	public function actionMonitor($q = 'queue:default', $showJobs = false)
	{
		/** @var Connection $q */
		$redis = Yii::$app->redis;

		$up = function (int $times = 1) {
			for ($i = 0; $i < $times; $i++) {
				echo "\r"; //start of line
				echo "\033[K"; //erase full line
				echo "\033[1A"; //move up
			}
		};

		while ($this->working) {
			$result = $redis->lrange($q, 0, -1);

			$out = [
				'jobs' => [],
				'countJobs' => sizeof($result),
			];

			if ($showJobs) {
				foreach ($result AS $item) {
					$uItem = Json::decode($item);
					$className = $uItem['job'];
					$data = Json::encode($uItem['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
					$out['jobs'][] = "{$className}" . " --> " . $data;
				}
			} else {
				$uniqueJobs = [];
				foreach ($result AS $item) {
					$uItem = Json::decode($item);
					$className = $uItem['job'];
					if (!isset($uniqueJobs[$className])) {
						$uniqueJobs[$className] = 1;
					} else {
						$uniqueJobs[$className]++;
					}
				}

				$out['jobs'] = $uniqueJobs;
			}
			$s = VarDumper::export($out);
			$countNewLines = substr_count($s, "\n");
			echo $s;
			sleep(2);
			$up($countNewLines);
			unset($result, $out, $s, $countNewLines);
		}
	}

	private function signalHandler($signo)
	{
		$this->working = false;
		Yii::info('SIGTERM received', __METHOD__);
	}
}
