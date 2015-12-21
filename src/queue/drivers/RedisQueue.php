<?php

namespace atlasmobile\queue\drivers;

use atlasmobile\queue\BaseQueue;
use atlasmobile\queue\Job;
use Yii;
use yii\base\InvalidConfigException;
use yii\redis\Connection;

/**
 * atlas. 2015
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class RedisBaseQueue extends BaseQueue
{
    /**
     * @var string|\Redis Default redis component name
     */
    public $redis = 'redis';

    /**
     * Class initialization logic
     *
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        if (is_string($this->redis)) {
            $this->redis = Yii::$app->get($this->redis);
        } elseif (is_array($this->redis)) {
            $this->redis = Yii::createObject($this->redis);
        }
        if (!$this->redis instanceof Connection) {
            throw new InvalidConfigException("Queue::redis must be either a Redis connection instance or the application component ID of a Redis connection.");
        }
    }

	public function popInternal($queue = null) {
		$payload = $this->redis->lpop($this->getQueue($queue));
		if ($payload) {
			//$this->redis->zadd($queue.':reserved', $this->getTime() + 60, $job);
			return new Job($this, $payload, $queue);
		}

		return null;
	}

    protected function pushInternal($payload, $queue = null, $options = [])
    {
        $this->redis->rpush($this->getQueue($queue), $payload);
        $payload = json_decode($payload, true);

        return $payload['id'];
    }
} 
