<?php
namespace atlasmobile\queue;
/**
 * yii2-queue. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
abstract class BaseTask implements QueueWorker
{

	/**
	 * @param Job $job
	 * @param QueuePayload $payload
	 */
	public function afterRun(Job $job, QueuePayload $payload) {
	}

	/**
	 * @param Job $job
	 * @param QueuePayload $payload
	 */
	public function beforeRun(Job $job, QueuePayload $payload) {
	}

	/**
	 * @param Job $job
	 * @param QueuePayload $payload
	 * @param \Exception $exception
	 */
	public function onFail(Job $job, QueuePayload $payload, \Exception $exception) {
	}
}