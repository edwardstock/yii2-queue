<?php
namespace atlasmobile\queue;
/**
 * yii2-queue. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 * Renamed from QueueHandler to QueueWorker
 * @since 1.0.5
 */
interface QueueWorker
{
	/**
	 * Job handler method.
	 * @param Job $job
	 * @param array|null $data
	 */
	public function run($job, $data);
}