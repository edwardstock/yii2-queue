<?php namespace atlasmobile\queue\console\controllers;

use atlasmobile\models\FailedJobs;
use atlasmobile\queue\Job;
use atlasmobile\queue\Queue;
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

	public function options($actionID) {
		return array_merge(parent::options($actionID), [
			'tries', 'sleep', 'queueName', 'queueObjectName', 'storeFailedJobs'
		]);
	}

	/**
	 * Process a job
	 *
	 * @throws \Exception
	 */
	public function actionWork() {
		$this->process($this->queueName, $this->queueObjectName);
	}

	/**
	 * @param string $queueName
	 * @param string $queueObjectName
	 * @return bool
	 * @throws \Exception
	 */
	protected function process($queueName, $queueObjectName) {
		/** @var Queue $queue */
		$queue = Yii::$app->{$queueObjectName};
		/** @var Job $job */
		$job = $queue->pop($queueName);

		if ($job instanceof Job) {
			try {
				$job->run();
				return true;
			} catch (\Exception $e) {
				Yii::error($e->getTraceAsString(), self::$TAG);
				$payload = $job->getEncodedPayload();

				if (!isset($payload->data)) {
					$payload->data = new \stdClass();
				}

				if (isset($payload->data) && isset($payload->data->tries) && $payload->data->tries < $this->tries) {
					$payload->data->tries++;
					Yii::info("Tries " . $payload->data->tries . PHP_EOL, self::$TAG);
				} else if (!isset($payload->data->tries)) {
					$payload->data->tries = 1;
					Yii::info("Tries " . $payload->data->tries . PHP_EOL, self::$TAG);
				} else {
					Yii::error("TRY #" . $payload->data->tries . ': ' . $e->getTraceAsString(), self::$TAG);
					if ($this->storeFailedJobs) {
						$this->storeFailed($payload->job, $payload->tries, $payload);
					}
					throw $e;
				}
				$queue->push($payload->job, $payload->data, $job->getQueueName());

				throw $e;
			}
		}
		return false;
	}

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
			$cmd = PHP_BINARY . ' ' . Yii::$app->basePath . '/../yii queue/work ' . ($this->queueName !== null ? $this->queueName . ' ' : 'default') . $this->queueObjectName;
			echo exec($cmd, $out);
			echo implode(PHP_EOL, $out);
			sleep($this->sleep);
		}
	}

	/**
	 * Creates table with failed jobs
	 */
	public function actionTableFailed() {
		$this->run('migrate/up', [
			'migrationPath' => '@vendor/atlasmobile/yii2-queue/migrations'
		]);
	}
}
