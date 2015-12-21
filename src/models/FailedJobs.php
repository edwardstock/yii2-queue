<?php
namespace atlasmobile\models;

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
	public static function add($class, $tries, $payload) {
		$failed = new FailedJobs();
		$failed->class = $class;
		$failed->tries = $tries;
		$failed->payload = $payload;
		$failed->log_time = time();
		$failed->save(false);
	}
}