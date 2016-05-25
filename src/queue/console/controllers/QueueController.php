<?php namespace atlasmobile\queue\console\controllers;

declare(ticks = 1);

use atlasmobile\queue\BaseQueue;
use atlasmobile\queue\Job;
use atlasmobile\queue\models\Delayed;
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
	private static $queue = null;
	/**
	 * @var int Queue listen sleeping time
	 */
	public $sleep = 1;
	/**
	 * @var int Tries count before job will stored to failed or flushed
	 */
	public $tries = 3;
	/**
	 * @var string Queue name
	 */
	public $queueName = 'default';
	/**
	 * @var string Yii config component name
	 */
	public $queueObjectName = 'queue';
	/**
	 * @var bool Stores failed jobs to db. See queue/failed-table, queue/failed, queue/failed-flush
	 */
	public $storeFailedJobs = false;
	/**
	 * @var int Poll frequency in seconds
	 */
	public $poolFreqSeconds = 30;
	/**
	 * @var float Count seconds between transfer tasks from delayed queue to your queue
	 */
	public $poolGapSeconds = 0;
	/**
	 * @var bool
	 */
	public $debug = false;
	/**
	 * @var bool
	 */
	private $working = true;

	public function init() {
		pcntl_signal(SIGTERM, [$this, 'signalHandler']);
		pcntl_signal_dispatch();
		parent::init();
		if (self::$queue === null) {
			self::$queue = Yii::$app->{$this->queueObjectName};
		}
	}

	public function beforeAction($action) {
		if ($this->storeFailedJobs === 'true' || $this->storeFailedJobs === '1') {
			$this->storeFailedJobs = true;
		}
		return parent::beforeAction($action);
	}

	public function options($actionID) {
		$options = [
			'delayed'        => [
				'debug',
			],
			'listen-delayed' => [
				'poolFreqSeconds',
				'poolGapSeconds',
				'debug',
			],
			'listen'         => [
				'sleep',
				'tries',
				'queueName',
				'queueObjectName',
				'storeFailedJobs',
				'debug'
			],
			'work'           => [
				'tries',
				'queueName',
				'queueObjectName',
				'storeFailedJobs',
				'debug',
				'sleep',
			],
		];
		$parent = parent::options($actionID);

		return array_merge($parent, ($options[$actionID] ?? []));
	}

	/**
	 * Handles last delayed jobs
	 */
	public function actionDelayed() {
		$this->processDelayed();
	}

	/**
	 * Creates table with delayed tasks
	 */
	public function actionDelayedTable() {
		$this->run('migrate/up', [
			'migrationPath' => '@vendor/atlasmobile/yii2-queue/src/queue/migrations/delayed'
		]);
	}

	public function actionDelayedTableDrop() {
		$this->run('migrate/down', [
			'migrationPath' => '@vendor/atlasmobile/yii2-queue/src/queue/migrations/delayed'
		]);
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
			'migrationPath' => '@vendor/atlasmobile/yii2-queue/src/queue/migrations/failed'
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
			$out = shell_exec($cmd);
			if ($this->debug && $out !== '' && $out !== null) {
				$this->stdout($out . PHP_EOL);
			}
			sleep($this->sleep);
		}
	}

	/**
	 * Handle all delayed jobs in infinite loop
	 */
	public function actionListenDelayed() {
		if ($this->poolFreqSeconds < 0) {
			$this->poolFreqSeconds = 1;
		}

		while ($this->working) {
			$this->actionDelayed();
			sleep($this->poolFreqSeconds);
		}
	}

	public function actionMonitor($q = 'queue:default', $showJobs = false) {
		/** @var Connection $redis */
		$redis = Yii::$app->redis;
		$queue = Yii::$app->queue;

		$up = function (int $times = 1) {
			for ($i = 0; $i < $times; $i++) {
				echo "\r"; //start of line
				echo "\033[K"; //erase full line
				echo "\033[1A"; //move up
			}
		};

		while ($this->working) {
			$result = $redis->lrange($q, 0, -1);
			$delayedCnt = (int)Delayed::find()->count();

			$out = [
				'jobs'      => [],
				'countJobs' => sizeof($result),
				'delayed'   => $delayedCnt,
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
				if ((int)$this->tries < 1) {
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

				$msg = "Error executing job. \n{$e->getMessage()}\n{$e->getTraceAsString()}\n\nData: " . VarDumper::export($payload->getParams()) . "\nTRY #" . $workedTries . PHP_EOL;
				if ($this->debug) {
					echo $msg;
				}
				Yii::warning($msg, self::$TAG);
				if ($workedTries < $this->tries) {
					$payload->setParam('tries', $workedTries + 1);
					$queue->push($payload->getClass(), $payload->getParams(), $job->getQueueName());
					if ($this->storeFailedJobs) {
						$this->storeFailed($payload->getClass(), $payload->getParam('tries'), $payload, $e);
					}
				} else {
					Yii::warning($msg, self::$TAG);
					throw $e;
				}

				throw $e;
			}
		}
		return false;
	}

	/**
	 * @return int Count of pushed jobs
	 */
	protected function processDelayed() {
		/** @var BaseQueue $queue */
		$queue = self::$queue;
		$sent = 0;

		foreach ($queue->getDelayedList() AS $delayedJob) {
			/** @var Job $delayedJob */
			if ($this->debug) {
				echo "Job time: " . $delayedJob->getPayload()->getDelayTime() . ': now time ' . time() . "\n";
			}
			if ((int)$delayedJob->getPayload()->getDelayTime() <= time()) {
				echo "Delayed job is out of time at " . (time() - (int)$delayedJob->getPayload()->getDelayTime()) . " seconds\n";
				$job = Delayed::pop($delayedJob->getPayload()->getId());
				if ($job !== null) {
					$queue->pushJob($delayedJob->getPayload());
					$sent++;
				} else {
					$this->stderr("But RPOP returned NULL ({$queue->delayedQueuePrefix})", Console::FG_RED);
				}
			}

			if ($this->poolGapSeconds > 0) {
				usleep($this->poolGapSeconds * 1000000);
			}
		}

		return $sent;
	}

	/**
	 * @return BaseQueue
	 */
	private function getQueue() {
		return Yii::$app->{$this->queueObjectName};
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

		foreach ($this->options($actionName) AS $option) {
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

	private function signalHandler($signo) {
		$this->working = false;
		Yii::info('SIGTERM received', __METHOD__);
	}
}
