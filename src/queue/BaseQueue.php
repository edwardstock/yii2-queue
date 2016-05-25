<?php
namespace atlasmobile\queue;

use Yii;
use yii\base\Component;
use const false;

/**
 * atlas. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
abstract class BaseQueue extends Component
{
	const DS_SQL = 'sql';
	const DS_REDIS = 'redis';

	/**
	 * @var string Queue prefix
	 */
	public $queuePrefix = 'queue';

	/**
	 * @var string
	 */
	public $delayedQueuePrefix = 'qdelayed';

	/** @var bool Debug mode */
	public $debug = false;

	/**
	 * @return \Generator|Job[]
	 */
	abstract public function getDelayedList();

	/**
	 * Class-specific realisation of getting the job to the queue
	 *
	 * @param string|null $queue Queue name
	 *
	 * @param bool $delayed
	 * @return mixed
	 */
	abstract protected function popInternal($queue = null, $delayed = false);

	/**
	 * Class-specific realisation of adding the job to the queue
	 *
	 * @param array $payload Job data
	 * @param string|null $queue Queue name
	 * @param array $options
	 *
	 * @param bool $delayed
	 * @return mixed
	 */
	abstract protected function pushInternal($payload, $queue = null, $options = [], $delayed = false);

	/**
	 * Builds queue prefix
	 *
	 * @param string|null $name Queue name
	 * @return string
	 */
	public function buildPrefix($name = null) {
		if (empty($name)) {
			$name = 'default';
		} elseif ($name && preg_match('/[^[:alnum:]]/', $name)) {
			$name = md5($name);
		}

		return $this->queuePrefix . ':' . $name;
	}

	/**
	 * Builds queue prefix
	 *
	 * @param string|null $name Queue name
	 * @return string
	 */
	public function buildPrefixDelayed($name = null) {
		if (empty($name)) {
			$name = 'default';
		} elseif ($name && preg_match('/[^[:alnum:]]/', $name)) {
			$name = md5($name);
		}

		return $this->delayedQueuePrefix . ':' . $name;
	}

	/**
	 * Get job from the queue
	 *
	 * @param string|null $queue Queue name
	 * @param bool $delayed
	 * @return mixed
	 */
	public function pop($queue = null, $delayed = false) {
		return $this->popInternal($queue, $delayed);
	}

	/**
	 * Push job to the queue
	 *
	 * @param string $job Fully qualified class name of the job
	 * @param mixed $data Data for the job
	 * @param string|null $queue Queue name
	 * @param array $options
	 * @return string ID of the job
	 */
	public function push($job, $data = null, $queue = null, $options = []) {
		return $this->pushInternal($this->createPayload($job, $data), $queue, $options);
	}

	/**
	 * @param $job
	 * @param mixed $delay integer timestamp or string supported by \DateTime. For example: +3 hours, +1 week
	 * @param null $data
	 * @param null $queue
	 * @param bool $unique
	 * @return mixed
	 */
	public function pushDelayed($job, $delay, $data = null, $queue = null, $unique = false) {
		return $this->pushInternal(
			$this->createPayloadDelayed($job, $data, $delay, $queue, $unique),
			$queue,
			[],
			true
		);
	}

	/**
	 * @param QueuePayload $payload
	 */
	public function pushJob(QueuePayload $payload) {
		$this->push($payload->getClass(), $payload->getParams(), $payload->getQueueName() ?? 'default');
	}

	/**
	 * @param Job $job
	 * @param QueuePayload|null $payload
	 * @param mixed $delay
	 */
	public function pushJobToDelayed(Job $job, QueuePayload $payload = null, $delay = null) {
		if ($payload === null) {
			$payload = $job->getPayload();
		}

		$this->pushDelayed($payload->getClass(), $delay ?? $payload->getDelayTime(), $payload->getParams(), $job->getQueueName());
	}

	/**
	 * Create job array
	 *
	 * @param string $job Fully qualified class name of the job
	 * @param mixed $data Data for the job
	 * @param bool $unique
	 * @return array
	 */
	protected function createPayload($job, $data, $unique = false) {
		$payload = [
			'job'    => $job,
			'data'   => $data,
			'unique' => $unique,
		];
		$payload = $this->setMeta($payload, 'id', $this->getRandomId());

		return $payload;
	}

	/**
	 * Set additional meta on a payload string.
	 *
	 * @param  string $payload
	 * @param  string $key
	 * @param  string $value
	 * @return string
	 */
	protected function setMeta($payload, $key, $value) {
		$payload[$key] = $value;

		return json_encode($payload);
	}

	/**
	 * Get random ID.
	 *
	 * @return string
	 */
	protected function getRandomId() {
		return Yii::$app->security->generateRandomString();
	}

	/**
	 * @param $job
	 * @param $data
	 * @param $delayTime
	 * @param null $queue
	 * @param bool $unique
	 * @return array|string
	 */
	protected function createPayloadDelayed($job, $data, $delayTime, $queue = null, $unique = false) {
		if (is_int($delayTime) || is_numeric($delayTime)) {
			$time = (int)$delayTime;
		} else if ($delayTime instanceof \DateTime) {
			$time = $delayTime->getTimestamp();
		} else {
			$time = (int)(new \DateTime($delayTime))->getTimestamp();
		}

		$payload = [
			'job'    => $job,
			'data'   => $data,
			'time'   => $time,
			'queue'  => $queue ?? 'default',
			'unique' => $unique,
		];
		$payload = $this->setMeta($payload, 'id', $this->getRandomId());

		return $payload;
	}

	/**
	 * Get prefixed queue name
	 *
	 * @param string $queue BaseQueue name
	 * @return string
	 */
	protected function getQueue($queue) {
		return $this->buildPrefix($queue);
	}
}
