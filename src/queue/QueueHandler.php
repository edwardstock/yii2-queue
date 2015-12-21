<?php
namespace atlasmobile\queue;
/**
 * yii2-queue. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
interface QueueHandler
{

	/**
	 * Job handler method.
	 * @param Job $job
	 * @param array|null $data
	 */
	public function run($job, $data);
}