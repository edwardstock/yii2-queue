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
	 * @var int
	 */
	private $delayTime = -1;

	private $queue = 'default';

	/**
	 * QueuePayload constructor.
	 * @param string $id
	 * @param string $className
	 * @param array $arguments
	 */
	public function __construct($id, $className, array $arguments, $delayTime = -1, $queue = 'default') {
		$this->id = $id;
		$this->className = $className;
		$this->arguments = $arguments;
		$this->delayTime = $delayTime;
		$this->queue = $queue;
	}

	/**
	 * @return string
	 */
	public function encode() {
		return base64_encode(serialize($this));
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
}