<?php
namespace atlasmobile\queue\models;

use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Query;
use yii\helpers\Json;

/**
 * yii2-queue. 2016
 * @property string id
 * @property string job
 * @property string data
 * @property int time
 * @property string queue
 * @property bool unique
 * @property mixed data_plain
 * @property string added_at
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class Delayed extends ActiveRecord
{
	/**
	 * @param $json
	 * @param string $jobName
	 * @return int
	 */
	public static function countByPlainData($json, $jobName = null) {
		return (int)self::findByPlainData($json, $jobName)->count();
	}

	/**
	 * @param string $json
	 * @param string $jobName
	 * @return bool
	 */
	public static function existsByPlainData($json, $jobName = null) {
		return (bool)self::findByPlainData($json, $jobName)->exists();
	}

	/**
	 * @param string $json
	 * @param null $jobName
	 * @return Query
	 */
	public static function findByPlainData($json, $jobName = null) {
		if (is_array($json)) {
			$plain = Json::encode($json);
		} else {
			$plain = $json;
		}

		$q = self::find();
		$q->andWhere(['unique' => true]);
		if ($jobName !== null) {
			$q->andWhere(['job' => $jobName]);
		}

		if (self::isPgsql()) {
			$q->andWhere('[[data_plain]]::text = :plain', [':plain' => $plain]);
		} else {
			$q->andWhere(['data_plain' => $plain]);
		}

		return $q;
	}

	/**
	 * @return \Generator|Delayed[]
	 */
	public static function getReady() {
		$q = self::find();
		$q->andWhere('([[time]] IS NOT NULL) AND ([[time]] <= NOW())');
		$q->orderBy(['added_at' => 'asc']);

		foreach ($q->batch() AS $tasks) {
			foreach ($tasks AS $task) {
				yield $task;
			}
		}
	}

	public static function countReady() {
		$q = self::find();
		$q->andWhere('([[time]] IS NOT NULL) AND ([[time]] <= NOW())');
		$q->orderBy(['added_at' => 'asc']);

		return (int)$q->count();
	}

	/**
	 * Takes and removes from db delayed queue
	 * @param $id
	 * @return null|static
	 * @throws \Exception
	 */
	public static function pop($id) {
		$item = self::findOne(['id' => $id]);
		if ($item === null) {
			return null;
		}

		$item->delete();
		return $item;
	}

	/**
	 * @param array $payload
	 * @return Delayed
	 * @throws Exception
	 */
	public static function push(array $payload) {
		$attributes = $payload;
		unset($attributes['id']);
		$delayed = new Delayed($attributes);

		if (!$delayed->save()) {
			throw new Exception('Cannot push delayed job', $delayed->getErrors());
		}

		return $delayed;
	}

	/**
	 * Generates uuid
	 * @return string
	 * @throws \yii\base\Exception
	 * @throws \yii\base\InvalidConfigException
	 */
	public static function guidv4() {
		if (function_exists('com_create_guid') === true)
			return trim(com_create_guid(), '{}');

		$data = \Yii::$app->security->generateRandomKey(16);
		if (PHP_VERSION)
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	public static function tableName() {
		return 'queue_delayed';
	}

	public function rules() {
		return [
			[['id', 'job'], 'required'],
			[['id', 'job', 'data', 'data_plain', 'queue'], 'string'],
			[['unique'], 'boolean'],
			[['added_at', 'time'], 'safe'],
		];
	}

	public function beforeValidate() {
		if ($this->isNewRecord) {
			$this->id = self::guidv4();
		}

		if (is_array($this->data)) {
			$this->data_plain = $this->buildPlainArray($this->data);
			$this->data = Json::encode($this->data, JSON_NUMERIC_CHECK);
		}
		if (is_array($this->data_plain)) {
			$this->data_plain = Json::encode($this->data_plain, JSON_NUMERIC_CHECK);
		}

		if (is_int($this->time) && self::getDb()->driverName === 'pgsql') {
			$dt = new \DateTime();
			$dt->setTimestamp($this->time);
			$this->time = $dt->format('Y-m-d H:i:s');
		}


		return parent::beforeValidate();
	}

	public function afterFind() {
		parent::afterFind();
		if (is_string($this->data)) {
			$this->data = Json::decode($this->data, true);
		}
		if (is_string($this->data_plain)) {
			$this->data_plain = Json::decode($this->data_plain, true);
		}
	}

	public function update($runValidation = true, $attributeNames = null) {
		throw new Exception('Cannot update task because its a restricted operation. Delete and reinsert if you want to modify data');
	}

	private static function isPgsql() {
		return self::getDb()->driverName === 'pgsql';
	}

	/**
	 * @return array|mixed
	 */
	public function getDecodedData() {
		if ($this->data === null || $this->data === '') {
			return [];
		} else if (is_array($this->data)) {
			return $this->data;
		}

		return Json::decode($this->data, true);
	}

	public function getDecodedDataPlain() {
		if ($this->data_plain === null || $this->data_plain === '') {
			return [];
		}

		return Json::decode($this->data_plain, true);
	}

	/**
	 * @return array
	 */
	public function getPayloadAttributes() {
		return [
			'id'     => $this->id,
			'job'    => $this->job,
			'time'   => is_string($this->time) ? \DateTime::createFromFormat('Y-m-d H:i:sO', $this->time)->getTimestamp() : $this->time,
			'data'   => $this->getDecodedData(),
			'queue'  => $this->queue,
			'unique' => $this->unique,
		];
	}

	/**
	 * @param \stdClass|string|array $data
	 * @return array
	 */
	private function buildPlainArray($data) {
		$out = [];
		$recForm = function ($value, $level = 0, $prevName = null) use (&$recForm, &$out) {
			foreach ($value AS $n => $v) {
				if (is_object($v)) {
					$nextName = $prevName . $n;

					if ($level >= 1) {
						$nextName = $prevName . ".{$n}";
					}

					$res = $recForm($v, $level + 1, $nextName);
					if ($res !== null) {
						$out[$nextName] = $res;
					}
				} else {
					if ($level === 1) {
						$name = $prevName !== null ? "{$prevName}.{$n}" : $n;
					} else if ($level > 1) {
						$name = "{$prevName}.{$n}";
					} else {
						$name = $n;
					}
					$out[$name] = $v;
				}
			}
		};

		if (is_object($data)) {
			$procData = $data;
		} else if (is_string($data)) {
			$procData = Json::decode($data, false);
		} else {
			$procData = Json::decode(Json::encode($data), false);
		}

		$recForm($procData);

		return $out;
	}
}