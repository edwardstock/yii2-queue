<?php
/**
 * yii2-queue. 2016
 * Date: 05.07.16
 * Time: 14:06
 */

namespace edwardstock\queue\drivers;


use edwardstock\queue\events\DriverEvent;
use edwardstock\queue\impl\yii\QueueComponent;
use edwardstock\queue\job\Job;
use edwardstock\queue\statistics\BaseStatManager;
use edwardstock\queue\statistics\DriverStatManager;
use yii\base\InvalidConfigException;

abstract class Driver extends QueueComponent
{
    const EVENT_BEFORE_PUSH = 'driverBeforePush';
    const EVENT_AFTER_PUSH  = 'driverAfterPush';
    const EVENT_BEFORE_POP  = 'driverBeforePop';
    const EVENT_AFTER_POP   = 'driverAfterPop';

    /**
     * @var DriverStatManager|BaseStatManager
     */
    private static $statManager = null;
    /**
     * @var bool
     */
    private $autoConnect = true;
    /**
     * @var bool
     */
    private $autoDisconnect = false;

    /**
     * @param Payload $payload
     *
     * @return void
     */
    abstract public function push($payload);

    /**
     * @param Payload[] $payloads
     *
     * @return mixed
     */
    abstract public function pushBatch(array $payloads);

    /**
     * @param string $queueName
     * @param bool   $wait Stopper for infinite loop
     *
     * @return mixed
     */
    abstract public function handleJobs(string $queueName, bool $wait = true);

    /**
     * @return mixed
     */
    abstract public function connect();

    /**
     * @return mixed
     */
    abstract public function disconnect();

    /**
     * @return string
     */
    abstract public function getId(): string;

    /**
     * Prepares driver to multiprocess usage
     * For example: prefetch in rabbitmq
     */
    abstract public function prepareMultiThreaded();

    /**
     * @return boolean
     */
    public function isAutoConnect(): bool
    {
        return $this->autoConnect;
    }

    /**
     * @param boolean $autoConnect
     */
    public function setAutoConnect(bool $autoConnect)
    {
        $this->autoConnect = $autoConnect;
    }

    /**
     * @return boolean
     */
    public function isAutoDisconnect(): bool
    {
        return $this->autoDisconnect;
    }

    /**
     * @param boolean $autoDisconnect
     */
    public function setAutoDisconnect(bool $autoDisconnect)
    {
        $this->autoDisconnect = $autoDisconnect;
    }

    /**
     * @return DriverStatManager
     */
    public function getStatManager(): DriverStatManager
    {
        if (self::$statManager === null) {
            self::$statManager = new DriverStatManager($this);
        }

        return self::$statManager;
    }

    /**
     * Dispatches signals while jobs working
     * By default, dispatches only pcntl signals
     */
    protected function dispatchSystemEvents()
    {
        pcntl_signal_dispatch();
    }

    /**
     * @return mixed
     * @throws InvalidConfigException
     */
    protected function getPayloadClass()
    {
        $map = QueueComponent::find()->driverPayloadMap;
        if (!isset($map[$this->getId()])) {
            throw new InvalidConfigException('Payload map for driver ' . $this->getId() . ' did not configured. See Queue::$driverPayloadMap.');
        }

        return $map[$this->getId()];
    }

    /**
     *
     */
    protected function onBeforePop()
    {
        $this->trigger(self::EVENT_BEFORE_POP, new DriverEvent([
            'driver' => $this,
        ]));
    }

    protected function onAfterPop(Job $job)
    {
        $this->trigger(self::EVENT_AFTER_POP, new DriverEvent([
            'driver' => $this,
            'job'    => $job,
        ]));
    }

    protected function onBeforePush(Payload $payload)
    {
        $this->trigger(self::EVENT_BEFORE_PUSH, new DriverEvent([
            'driver'  => $this,
            'payload' => $payload,
        ]));
    }

    protected function onAfterPush(Payload $payload)
    {
        $this->trigger(self::EVENT_AFTER_PUSH, new DriverEvent([
            'driver'  => $this,
            'payload' => $payload,
        ]));
    }
}