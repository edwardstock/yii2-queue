<?php
namespace atlasmobile\queue;

use yii\base\Arrayable;

/**
 * yii2-queue. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class QueuePayload implements Arrayable
{
	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var string Class name
	 */
	private $className;

	/**
	 * @var array
	 */
	private $arguments = [];

	/**
	 * @var int
	 */
	private $delayTime = -1;

	/**
	 * @var bool
	 */
	private $unique = false;

	private $queue = 'default';

	/**
	 * QueuePayload constructor.
	 * @param string $id
	 * @param string $className
	 * @param array $arguments
	 * @param int $delayTime
	 * @param string $queue
	 * @param bool $unique
	 */
	public function __construct($id, $className, array $arguments, $delayTime = -1, $queue = 'default', $unique = false) {
		$this->id = $id;
		$this->className = $className;
		$this->arguments = $arguments;
		$this->delayTime = $delayTime;
		$this->queue = $queue;
		$this->unique = $unique;
	}

	/**
	 * @return string
	 */
	public function encode() {
		return base64_encode(serialize($this));
	}

	/**
	 * Returns the list of additional fields that can be returned by [[toArray()]] in addition to those listed in [[fields()]].
	 *
	 * This method is similar to [[fields()]] except that the list of fields declared
	 * by this method are not returned by default by [[toArray()]]. Only when a field in the list
	 * is explicitly requested, will it be included in the result of [[toArray()]].
	 *
	 * @return array the list of expandable field names or field definitions. Please refer
	 * to [[fields()]] on the format of the return value.
	 * @see toArray()
	 * @see fields()
	 */
	public function extraFields() {
		return [];
	}

	/**
	 * Returns the list of fields that should be returned by default by [[toArray()]] when no specific fields are specified.
	 *
	 * A field is a named element in the returned array by [[toArray()]].
	 *
	 * This method should return an array of field names or field definitions.
	 * If the former, the field name will be treated as an object property name whose value will be used
	 * as the field value. If the latter, the array key should be the field name while the array value should be
	 * the corresponding field definition which can be either an object property name or a PHP callable
	 * returning the corresponding field value. The signature of the callable should be:
	 *
	 * ```php
	 * function ($model, $field) {
	 *     // return field value
	 * }
	 * ```
	 *
	 * For example, the following code declares four fields:
	 *
	 * - `email`: the field name is the same as the property name `email`;
	 * - `firstName` and `lastName`: the field names are `firstName` and `lastName`, and their
	 *   values are obtained from the `first_name` and `last_name` properties;
	 * - `fullName`: the field name is `fullName`. Its value is obtained by concatenating `first_name`
	 *   and `last_name`.
	 *
	 * ```php
	 * return [
	 *     'email',
	 *     'firstName' => 'first_name',
	 *     'lastName' => 'last_name',
	 *     'fullName' => function ($model) {
	 *         return $model->first_name . ' ' . $model->last_name;
	 *     },
	 * ];
	 * ```
	 *
	 * @return array the list of field names or field definitions.
	 * @see toArray()
	 */
	public function fields() {
		return array_keys(get_object_vars($this));
	}

	/**
	 * @return string
	 */
	public function getClass() {
		return $this->className;
	}

	/**
	 * @return int
	 */
	public function getDelayTime() {
		return $this->delayTime;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return bool
	 */
	public function getIsUnique() {
		return $this->unique;
	}

	/**
	 * @param string $key
	 * @param null|mixed $default
	 * @return mixed|null
	 */
	public function getParam($key, $default = null) {
		if (!$this->hasParam($key)) {
			return $default;
		}

		return $this->arguments[$key];
	}

	/**
	 * @return array
	 */
	public function getParams() {
		return $this->arguments;
	}

	/**
	 * @return string
	 */
	public function getQueueName() {
		return $this->queue;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasParam($key) {
		return isset($this->arguments[$key]);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function setParam($key, $value) {
		if (!is_array($this->arguments)) {
			$this->arguments = [];
		}

		$this->arguments[$key] = $value;
	}

	/**
	 * @param array $val
	 */
	public function setParams(array $val) {
		$this->arguments = $val;
	}

	/**
	 * @param $unique
	 */
	public function setUnique($unique) {
		$this->unique = $unique;
	}

	/**
	 * Converts the object into an array.
	 *
	 * @param array $fields the fields that the output array should contain. Fields not specified
	 * in [[fields()]] will be ignored. If this parameter is empty, all fields as specified in [[fields()]] will be returned.
	 * @param array $expand the additional fields that the output array should contain.
	 * Fields not specified in [[extraFields()]] will be ignored. If this parameter is empty, no extra fields
	 * will be returned.
	 * @param boolean $recursive whether to recursively return array representation of embedded objects.
	 * @return array the array representation of the object
	 */
	public function toArray(array $fields = [], array $expand = [], $recursive = true) {
		$out = [];
		$customFields = sizeof($fields) > 0;
		foreach (get_object_vars($this) AS $field => $value) {
			if ($field === 'id') continue;
			if ($customFields && in_array($field, $fields)) {
				$out[$field] = $value;
				continue;
			}

			$out[$field] = $value;
		}

		return $out;
	}
}
