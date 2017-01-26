<?php

namespace edwardstock\queue;

use edwardstock\queue\console\controllers\QueueController;
use edwardstock\queue\drivers\Driver;
use edwardstock\queue\drivers\Payload;
use edwardstock\queue\drivers\rabbitmq\RabbitPayload;
use edwardstock\queue\drivers\redis\RedisPayload;
use edwardstock\queue\exceptions\DriverNotConfiguredException;
use edwardstock\queue\exceptions\QueueRuntimeException;
use edwardstock\queue\impl\log\CliLogger;
use edwardstock\queue\impl\yii\QueueComponent;
use edwardstock\queue\statistics\BaseStatManager;
use yii\base\InvalidConfigException;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 * Usage:
 * In your Yii2 config file
 * 'queue' => [
 *     'class'   => \edwardstock\queue\Queue::class,
 *     'drivers' => [
 *         [
 *             'class'    => \edwardstock\queue\drivers\rabbitmq\RabbitDriver::class,
 *             'host'     => 'localhost',
 *             'port'     => 5672,
 *             'user'     => 'edwardstock',
 *             'password' => 'subwoofer',
 *             'vhost'    => '/',
 *         ],
 *         [
 *             'class'     => \edwardstock\queue\drivers\redis\RedisDriver::class,
 *             'component' => 'redis',
 *
 *             // this lines needed if you didn't configured redis component
 *             'connection'=>[
 *             'class' => 'yii\redis\Connection',
 *             'hostname' => 'localhost',
 *             'port' => 6379,
 *             'database' => 0,
 *
 *         ]
 *     ],
 * ],
 *
 *
 */
class Queue extends QueueComponent
{
    /**
     * @var array Cached payloads
     */
    private static $stateCache = [];

    /**
     * @var int Payloads cache time (in seconds)
     */
    private static $stateCacheTTL = 5;

    /**
     * @var int Time spent from last fetching payloads from file
     */
    private static $stateCacheTime = 0;

    /**
     * @var bool
     */
    public $debug = true;

    /**
     * @var bool Delete from queue messages if error occurred while executing job
     */
    public $deleteOnError = true;

    /**
     * @var array
     */
    public $driverPayloadMap = [
        'rabbitmq' => RabbitPayload::class,
        'redis'    => RedisPayload::class,
    ];

    /**
     * @var CliLogger
     */
    private $loggerInstance = null;

    /**
     * @var array[] Drivers config.
     * @see setDrivers()
     *
     */
    private $drivers = [];

    /**
     * @var Driver[]
     */
    private $driversInstances = [];

    /**
     * @var BaseStatManager[]
     * @see attachStatManager()
     * @see detachStatManager()
     */
    private $statistics = [];

    public function init()
    {
        parent::init();

        if (sizeof($this->drivers) === 0) {
            throw new InvalidConfigException('At least one driver must be configured');
        }

        foreach ($this->drivers AS $driver) {
            /** @var Driver $instance */
            $instance                                   = \Yii::createObject($driver);
            $this->driversInstances[$instance->getId()] = $instance;
        }

        \Yii::$app->controllerMap['queue'] = [
            'class' => QueueController::class,
        ];
    }

    /**
     * Managers are not stacks
     *
     * @param BaseStatManager $statManager
     */
    public function attachStatManager(BaseStatManager $statManager)
    {
        $this->statistics[$statManager->getId()] = $statManager;
        $this->statistics[$statManager->getId()]->attachEventHandlers($this);
    }

    /**
     * @param string|BaseStatManager $id
     */
    public function detachStatManager($id)
    {
        $statId = $id instanceof BaseStatManager ? $id->getId() : $id;
        if (!isset($this->statistics[$statId])) {
            return;
        }

        $manager = $this->statistics[$statId];
        $manager->detachEventHandlers();

        unset($manager, $this->statistics[$statId]);
    }

    /**
     * Close connections for all connected drivers
     */
    public function disconnectDrivers()
    {
        foreach ($this->driversInstances AS $driver) {
            $driver->disconnect();
        }
    }

    /**
     * @param string $id
     *
     * @return Driver
     * @throws DriverNotConfiguredException
     */
    public function getDriver(string $id): Driver
    {
        foreach ($this->driversInstances AS $name => $driver) {
            if ($name === $id) {
                return $driver;
            }
        }

        throw new DriverNotConfiguredException('Driver with id ' . $id . ' did not configured yet');
    }

    /**
     * @return Driver[]
     */
    public function getDrivers(): array
    {
        return $this->driversInstances;
    }

    /**
     * @param array[] $drivers
     */
    public function setDrivers(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * @return string[] Ids of drivers
     */
    public function getDriversNames()
    {
        return array_keys($this->driversInstances);
    }

    /**
     * @return CliLogger
     */
    public function getLogger(): CliLogger
    {
        if ($this->loggerInstance === null) {
            $this->loggerInstance = new CliLogger($this->debug);
        }

        return $this->loggerInstance;
    }

    /**
     * @param string  $driverId
     * @param string  $queueName
     * @param Payload $fallback
     *
     * @return Payload
     */
    public function getSavedPayload(string $driverId, string $queueName, Payload $fallback = null)
    {
        $saved = $this->getSavedPayloads();

        return $saved[$driverId][$queueName] ?? $fallback;
    }

    /**
     * @param bool $cached
     *
     * @return array
     */
    public function getSavedPayloads(bool $cached = true): array
    {
        if ($cached && self::$stateCacheTime > time()) {
            return self::$stateCache;
        }

        $lockFile = \Yii::getAlias('@runtime/queue.state');
        if (!file_exists($lockFile)) {
            self::$stateCache = [];

            return [];
        }

        $content = file_get_contents($lockFile);
        $existed = unserialize($content);

        if ($existed === false || $existed === '' || $existed === null) {
            self::$stateCache = [];

            return [];
        }

        self::$stateCacheTime = time() + self::$stateCacheTTL;
        self::$stateCache     = $existed;

        return $existed;
    }

    /**
     * @return BaseStatManager[]
     */
    public function getStatManagers(): array
    {
        return $this->statistics;
    }

    /**
     * @param Payload $payload
     */
    public function push(Payload $payload)
    {
        $this->getLogger()->profileStart('Saving payload');
        $this->savePayload($payload);
        $this->getLogger()->profileEnd('Saving payload');

        $this->getLogger()->profileStart('Push single payload');
        $this->getDriver($payload->getDriverId())->push($payload);
        $this->getLogger()->profileEnd('Push single payload');
    }

    /**
     * @param Payload[] $payloads
     */
    public function pushBatch(array $payloads)
    {
        $split = [];
        $this->getLogger()->profileStart('Saving payloads in batch');
        foreach ($payloads AS $payload) {
            $split[$payload->getDriverId()][] = $payload;
            $this->savePayload($payload);
        }
        $this->getLogger()->profileEnd('Saving payloads in batch');

        foreach ($split AS $driverId => $payloads) {
            $this->getLogger()->profileStart("Push via $driverId driver (" . sizeof($payloads) . " items)");
            $this->getDriver($driverId)->pushBatch($payloads);
            $this->getLogger()->profileEnd("Push via $driverId driver (" . sizeof($payloads) . " items)");
        }
    }

    /**
     * @param string $driverId
     * @param string $queueName
     * @param int    $timeout -1 - infinity loop, else throws runtime exception
     *
     * @return Payload
     * @throws QueueRuntimeException
     */
    public function waitForPayload(string $driverId, string $queueName, int $timeout = -1)
    {
        $tries = 0;
        while (($payload = $this->getSavedPayload($driverId, $queueName, null)) === null) {
            if ($timeout > -1 && $tries >= $timeout) {
                throw new QueueRuntimeException('Time is out for waiting payload');
            }
            sleep(1);
            $tries++;
            $this->getLogger()->info("Waiting for payload load...");
        }

        return $payload;
    }

    private function savePayload(Payload $payload)
    {
        $id       = $payload->getDriverId();
        $queue    = $payload->getQueue();
        $lockFile = \Yii::getAlias('@runtime/queue.state');
        if (!file_exists($lockFile)) {
            touch($lockFile);
        }

        $key = $queue;

        try {
            $existed = unserialize(file_get_contents($lockFile));
            if ($existed === false || $existed === '' || $existed === null) {
                $newData = [
                    $id => [
                        $key => $payload,
                    ],
                ];

                $newData = serialize($newData);
                file_put_contents($lockFile, $newData, LOCK_EX);

                return;
            }

            $existed[$id][$key] = $payload;
            $existed            = serialize($existed);
            file_put_contents($lockFile, $existed, LOCK_EX);
        } catch (\Throwable $e) {
            $this->getLogger()->error($e);
            throw $e;
        }
    }
}