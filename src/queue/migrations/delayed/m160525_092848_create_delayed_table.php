<?php
use yii\db\Migration;

/**
 * Handles the creation for table `delayed_table`.
 */
class m160525_092848_create_delayed_table extends Migration
{
	/**
	 * @inheritdoc
	 */
	public function safeUp() {
		if ($this->db->driverName === 'mysql') {
			$mysql = <<<SQL
			CREATE TABLE queue_delayed
			(
			    id BINARY(36) PRIMARY KEY,
			    job TEXT NOT NULL,
			    data LONGTEXT DEFAULT NULL,
			    data_plain LONGTEXT DEFAULT NULL ,
			    time TIMESTAMP NOT NULL,
			    queue VARCHAR(255) DEFAULT 'default' NOT NULL,
			    `UNIQUE` TINYINT(1) DEFAULT 0 NOT NULL,
			    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			    PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SQL;

			$this->execute($mysql);
			$this->createIndex('queue_delayed_time_index', 'queue_delayed', ['time']);
			$this->createIndex('queue_delayed_added_at_unique_index', 'queue_delayed', ['added_at', 'unique']);
			$this->createIndex('queue_delayed_added_at_index', 'queue_delayed', ['added_at']);

		} else if ($this->db->driverName = 'pgsql') {
			$pgsql = <<<SQL
			CREATE TABLE queue_delayed
			(
			    id UUID PRIMARY KEY,
			    job TEXT NOT NULL,
			    data JSON DEFAULT NULL ,
			    data_plain JSON DEFAULT NULL ,
			    time TIMESTAMPTZ NOT NULL,
			    queue VARCHAR(255) DEFAULT 'default' NOT NULL,
			    "unique" BOOLEAN DEFAULT FALSE ,
			    added_at TIMESTAMP DEFAULT now()
			);
SQL;
			$this->execute($pgsql);
			$this->createIndex('queue_delayed_time_index', 'queue_delayed', ['time']);
			$this->createIndex('queue_delayed_added_at_index', 'queue_delayed', ['added_at']);
			$this->createIndex('queue_delayed_added_at_unique_index', 'queue_delayed', ['added_at', 'unique']);
		} else {
			throw new \yii\db\Exception('Database is not supporting. Please use MySQL or PostgreSQL');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown() {
		$this->execute('DROP TABLE {{queue_delayed}} CASCADE');
	}
}
