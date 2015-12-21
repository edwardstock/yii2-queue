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
	 * @var string Serialized object with job info
	 */
	protected $payload;

	/**
	 * @var string Queue name in redis
	 */
	protected $queueName;

	/**
	 * Job constructor.
	 * @param string $queueObject
	 * @param string $payload
	 * @param string $queueName
	 */
	public function __construct($queueObject, $payload, $queueName) {
		$this->queueObject = $queueObject;
		$this->payload = $payload;
		$this->queueName = $queueName;
	}

	public function run() {
		$this->resolveAndRun(json_decode($this->payload, true));
	}

	/**
	 * @param array $payload
	 * @throws \yii\base\InvalidConfigException
	 */
	protected function resolveAndRun(array $payload) {
		list($class, $method) = $this->resolveJob($payload['job']);
		$instance = Yii::createObject([
			'class' => $class
		]);
		$instance->{$method}($this, $payload['data']);
	}

	/**
	 * @param string $job
	 * @return array
	 */
	protected function resolveJob($job) {
		$segments = explode('@', $job);
		return count($segments) > 1 ? $segments : [$segments[0], 'run'];
	}

	/**
	 * @return string
	 */
	public function getQueueObject() {
		return $this->queueObject;
	}

	/**
	 * @return mixed
	 */
	public function getEncodedPayload() {
		return json_decode($this->getPayload(), false);
	}

	/**
	 * @return string
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
}
