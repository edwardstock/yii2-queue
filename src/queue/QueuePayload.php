<?php
namespace atlasmobile\queue;
/**
 * yii2-queue. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class QueuePayload
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
	 * QueuePayload constructor.
	 * @param string $id
	 * @param string $className
	 * @param array $arguments
	 */
	public function __construct($id, $className, array $arguments) {
		$this->id = $id;
		$this->className = $className;
		$this->arguments = $arguments;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getClass() {
		return $this->className;
	}

	/**
	 * @return array
	 */
	public function getParams() {
		return $this->arguments;
	}

	/**
	 * @param array $val
	 */
	public function setParams(array $val) {
		$this->arguments = $val;
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
	 * @param string $key
	 * @return bool
	 */
	public function hasParam($key) {
		return isset($this->arguments[$key]);
	}
}