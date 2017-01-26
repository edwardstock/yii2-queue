<?php

namespace edwardstock\queue\events;

use edwardstock\queue\drivers\Driver;
use edwardstock\queue\drivers\Payload;
use edwardstock\queue\drivers\rabbitmq\RabbitPayload;
use edwardstock\queue\drivers\redis\RedisPayload;
use edwardstock\queue\job\CallbackJob;
use edwardstock\queue\job\ClassJob;
use edwardstock\queue\job\Job;
use edwardstock\queue\job\ShellJob;
use yii\base\Event;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class DriverEvent extends Event
{
    /**
     * @var Driver
     */
    private $driver;
    /**
     * @var Job|ClassJob|ShellJob|CallbackJob
     */
    private $job = null;
    /**
     * @var Payload|RedisPayload|RabbitPayload
     */
    private $payload = null;

    /**
     * @return Driver
     */
    public function getDriver(): Driver
    {
        return $this->driver;
    }

    /**
     * @param Driver $driver
     */
    public function setDriver(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @return CallbackJob|ClassJob|Job|ShellJob
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @param CallbackJob|ClassJob|Job|ShellJob $job
     */
    public function setJob(Job $job)
    {
        $this->job = $job;
    }

    /**
     * @return Payload|RabbitPayload|RedisPayload
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @param Payload|RabbitPayload|RedisPayload $payload
     */
    public function setPayload(Payload $payload)
    {
        $this->payload = $payload;
    }
}