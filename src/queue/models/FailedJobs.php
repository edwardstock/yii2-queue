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
	 * @param $payload
	 */
	public static function add($class, $tries, QueuePayload $payload) {
		$failed = new FailedJobs();
		$failed->class = $class;
		$failed->tries = $tries;
		$failed->payload = $payload->encode();
		$failed->log_time = time();
		$failed->save(false);
	}
}