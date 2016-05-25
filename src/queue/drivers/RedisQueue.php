<?php

namespace atlasmobile\queue\drivers;

use atlasmobile\queue\BaseQueue;
use atlasmobile\queue\Job;
use atlasmobile\queue\models\Delayed;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\redis\Connection;

/**
 * atlas. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class RedisQueue extends BaseQueue
{
	/**
	 * @var string|\Redis Default redis component name
	 */
	public $redis = 'redis';

	/**
	 * Class initialization logic
	 *
	 * @throws InvalidConfigException
	 */
	public function init() {
		parent::init();
		if (is_string($this->redis)) {
			$this->redis = Yii::$app->get($this->redis);
		} elseif (is_array($this->redis)) {
			$this->redis = Yii::createObject($this->redis);
		}
		if (!$this->redis instanceof Connection) {
			throw new InvalidConfigException("Queue::redis must be either a Redis connection instance or the application component ID of a Redis connection.");
		}
	}

	/**
	 * @return \Generator|Job[]
	 */
	public function getDelayedList() {
		$list = Delayed::getReady();

		foreach ($list AS $payload) {
			yield new Job($this, $payload->getPayloadAttributes(), $payload->queue);
		}
	}

	/**
	 * @param string|null $queue
	 * @param bool $delayed
	 * @return Job|null
	 */
	public function popInternal($queue = null, $delayed = false) {
		$payload = $this->redis->lpop($this->getQueue($queue));
		if ($payload) {
			return new Job($this, $payload, $queue);
		}

		return null;
	}

	/**
	 * @param array $payload
	 * @param null $queue
	 * @param array $options
	 * @param bool $delayed
	 * @return null
	 */
	protected function pushInternal($payload, $queue = null, $options = [], $delayed = false) {
		$decoded = Json::decode($payload);
		$data = $decoded['data'] ?? [];

		if ($decoded['unique'] && $this->existsDelayed($data)) {
			return null;
		}

		if ($delayed) {
			$delayedJob = Delayed::push($decoded);
			return $delayedJob->id;
		} else {
			$qName = $delayed ? $this->delayedQueuePrefix : $this->getQueue($queue);
			$this->redis->rpush($qName, $payload);
			$payload = json_decode($payload, true);

			return $payload['id'];
		}
	}

	/**
	 * @param array $criteria
	 * @return bool
	 */
	public function existsDelayed(array $criteria): bool {
		return Delayed::existsByPlainData($this->buildPlainArray($criteria));
	}

	/**
	 * @param array $criteria
	 * @return Job[]
	 */
	public function findDelayed(array $criteria): array {
		if (sizeof($criteria) === 0) {
			return [];
		}

		$search = [];
		$where = $this->buildPlainArray($criteria);
		foreach (Delayed::findByPlainData($where)->all() AS $payload) {
			/** @var Delayed $payload */
			$search[] = new Job($this, $payload->getPayloadAttributes(), $payload->queue);
		}

		return $search;
	}

	/**
	 * @param \stdClass|string|array $data
	 * @return array
	 */
	private function buildPlainArray($data) {
		$out = [];
		$recForm = function ($value, $level = 0, $prevName = null) use (&$recForm, &$out) {
			foreach ($value AS $n => $v) {
				if (is_object($v)) {
					$nextName = $prevName . $n;

					if ($level >= 1) {
						$nextName = $prevName . ".{$n}";
					}

					$res = $recForm($v, $level + 1, $nextName);
					if ($res !== null) {
						$out[$nextName] = $res;
					}
				} else {
					if ($level === 1) {
						$name = $prevName !== null ? "{$prevName}.{$n}" : $n;
					} else if ($level > 1) {
						$name = "{$prevName}.{$n}";
					} else {
						$name = $n;
					}
					$out[$name] = $v;
				}
			}
		};

		if (is_object($data)) {
			$procData = $data;
		} else if (is_string($data)) {
			$procData = Json::decode($data, false);
		} else {
			$procData = Json::decode(Json::encode($data), false);
		}

		$recForm($procData);

		return $out;
	}
} 
