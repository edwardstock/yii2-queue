<?php
namespace atlasmobile\queue;

use Yii;

/**
 * atlas. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class Job
{
	/**
	 * @var string Component name
	 */
	protected $queueObject;

	/**
	 * @var QueuePayload Serialized object with job info
	 */
	protected $payload;

	/**
	 * @var string Queue name in redis
	 */
	protected $queueName;

	/**
	 * Job constructor.
	 * @param string $queueObject
	 * @param string|array $payload
	 * @param string $queueName
	 */
	public function __construct($queueObject, $payload, $queueName) {
//		var_dump($payload);exit;
		if (is_array($payload)) {
			$payload = json_encode($payload);
		}
		$this->queueObject = $queueObject;
		$decoded = json_decode($payload);
		$delay = isset($decoded->time) ? $decoded->time : -1;
		$queueName = isset($payload->queue) ? $payload->queue : 'default';
		$this->payload = new QueuePayload($decoded->id, $decoded->job, (array)$decoded->data, $delay, $queueName);
		$this->queueName = $queueName;
	}

	/**
	 * @return string Serialized payload object
	 */
	public function getEncodedPayload() {
		return serialize($this->getPayload());
	}

	/**
	 * @return QueuePayload
	 */
	public function getPayload() {
		return $this->payload;
	}

	/**
	 * @return string
	 */
	public function getQueueName() {
		return $this->queueName;
	}

	/**
	 * @return string
	 */
	public function getQueueObject() {
		return $this->queueObject;
	}

	public function run() {
		$this->resolveAndRun($this->payload);
	}

	/**
	 * @param QueuePayload $payload
	 * @throws \Exception
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function resolveAndRun(QueuePayload $payload) {
		list($class, $method) = $this->resolveJob($payload->getClass());

		$instance = Yii::createObject([
			'class' => $class
		]);

		if ($instance instanceof BaseTask) {
			$instance->beforeRun($this, $payload);
		}

		try {
			if ($instance instanceof QueueWorker) {
				$instance->run($this, $payload->getParams());
			} else {
				call_user_func([$instance, $method], $this, $payload->getParams());
			}
		} catch (\Exception $ex) {
			if ($instance instanceof BaseTask) {
				$instance->onFail($this, $payload, $ex);
			}

			throw $ex;
		}


		if ($instance instanceof BaseTask) {
			$instance->afterRun($this, $payload);
		}
	}

	/**
	 * @param string $job
	 * @return array
	 */
	protected function resolveJob($job) {
		$segments = explode('@', $job);
		return count($segments) > 1 ? $segments : [$segments[0], 'run'];
	}
}
