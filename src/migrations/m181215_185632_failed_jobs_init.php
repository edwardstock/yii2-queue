<?php
use yii\db\Migration;
use yii\db\Schema;

class m181215_185632_failed_jobs_init extends Migration
{
	public function up() {
		$this->createTable('failed_queue_jobs', [
			'id'       => Schema::TYPE_BIGPK,
			'class'    => Schema::TYPE_STRING,
			'tries'    => Schema::TYPE_STRING,
			'log_time' => Schema::TYPE_DOUBLE,
			'payload'  => Schema::TYPE_INTEGER,
		]);
	}

	public function down() {
		$this->dropTable('failed_queue_jobs');
	}
}
