<?php
namespace atlasmobile\queue\helpers;

use atlasmobile\queue\BaseQueue;
use atlasmobile\queue\Job;

/**
 * yii2-queue. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class Queue
{
	/**
	 * @param string $job Class name of job handler. Class must implements QueueHandler
	 * @param null $data
	 * @param string $queue
	 * 
	 */
	public static function push($job, $data = null, $queue = 'default') {
		$preparedData = [];
		if (is_array($data)) {
			foreach ($data AS $k => $v) {
				if (is_object($v)) {
					$preparedData[$k] = serialize($v);
				} else {
					$preparedData[$k] = $v;
				}
			}
		} else {
			$preparedData = null;
		}

		self::getQueue()->push($job, $preparedData, $queue, []);
	}

	/**
	 * @param $job
	 * @param mixed $delay
	 * @param array $data
	 * @param string $queue
	 */
	public static function pushDelayed($job, $delay, $data = null, $queue = 'default') {
		$preparedData = [];
		if (is_array($data)) {
			foreach ($data AS $k => $v) {
				if (is_object($v)) {
					$preparedData[$k] = serialize($v);
				} else {
					$preparedData[$k] = $v;
				}
			}
		} else {
			$preparedData = null;
		}

		self::getQueue()->pushDelayed($job, $delay, $preparedData, $queue);
	}

	/**
	 * Checks delayed queue to existed tasks with data -> $data, adds to queue if not found
	 * @param $job
	 * @param $delay
	 * @param null $data
	 * @param string $queue
	 */
	public static function pushDelayedUnique($job, $delay, $data = null, $queue = 'default') {
		$preparedData = [];
		if (is_array($data)) {
			foreach ($data AS $k => $v) {
				if (is_object($v)) {
					$preparedData[$k] = serialize($v);
				} else {
					$preparedData[$k] = $v;
				}
			}
		} else {
			$preparedData = null;
		}

		self::getQueue()->pushDelayed($job, $delay, $preparedData, $queue, true);
	}

	/**
	 * @param string $queueName
	 * @return Job|null
	 */
	public static function pop($queueName = 'default') {
		return self::getQueue()->pop($queueName);
	}

	/**
	 * @return BaseQueue
	 */
	private static function getQueue() {
		return \Yii::$app->queue;
	}
}