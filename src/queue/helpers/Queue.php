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
	 * @param string $job Class name of job handler. Class must implements Queueable
	 * @param null $data
	 * @param string $queue
	 * @param array $options
	 */
	public static function push($job, $data = null, $queue = 'default', $options = []) {
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

		self::getQueue()->push($job, $preparedData, $queue, $options);
	}

	/**
	 * @return BaseQueue
	 */
	private static function getQueue() {
		return \Yii::$app->queue;
	}

	/**
	 * @param string $queueName
	 * @return Job|null
	 */
	public static function pop($queueName = 'default') {
		return self::getQueue()->pop($queueName);
	}
}