<?php
namespace atlasmobile\queue\models;

use atlasmobile\queue\QueuePayload;
use yii\db\ActiveRecord;

/**
 * yii2-queue. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 * @property int $id
 * @property string class
 * @property int tries
 * @property int log_time
 * @property string payload
 * @property string stack_trace
 */
class FailedJobs extends ActiveRecord
{

	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return 'failed_queue_jobs';
	}

	/**
	 * @param $class
	 * @param $tries
	 * @param QueuePayload $payload
	 * @param \Exception $exception
	 */
	public static function add($class, $tries, QueuePayload $payload, \Exception $exception) {
		$failed = new FailedJobs();
		$failed->class = $class;
		$failed->tries = $tries;
		$failed->payload = $payload->encode();
		$failed->log_time = time();
		$failed->stack_trace = $exception->getMessage();
		$failed->stack_trace .= "\n\n" . $exception->getTraceAsString();
		$failed->save(false);
	}
}