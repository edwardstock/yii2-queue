<?php

namespace edwardstock\queue\drivers\redis;

use edwardstock\queue\drivers\Driver;
use edwardstock\queue\drivers\Payload;
use edwardstock\queue\helpers\ArrayHelper;
use edwardstock\queue\impl\yii\QueueComponent;
use edwardstock\queue\job\Job;
use yii\base\InvalidConfigException;
use yii\helpers\Json;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class RedisDriver extends Driver
{
    /**
     * @var string|array Component name for redis or connection array config
     */
    public $component = null;

    /**
     * @var \yii\redis\Connection
     */
    private $redis;

    /**
     * @var int Prefetch emulation. Count of jobs to run at one time
     */
    private $prefetchCount = 0;

    /**
     * @var bool Use BLPOP instead of LPOP to get queue item
     */
    private $blockingQueue = false;

    public function init()
    {
        parent::init();

        if (is_string($this->component)) {
            $this->redis = \Yii::$app->get($this->component);
        } else {
            if (is_array($this->component) && sizeof($this->component) > 0) {
                $this->redis = \Yii::createObject($this->component);
            } else {
                throw new InvalidConfigException('Invalid configuration for Redis');
            }
        }
    }

    /**
     * @return int
     */
    public function getPrefetchCount(): int
    {
        return $this->prefetchCount;
    }

    /**
     * @param int $prefetchCount
     */
    public function setPrefetchCount(int $prefetchCount)
    {
        $this->prefetchCount = $prefetchCount;
    }

    /**
     * @return boolean
     */
    public function isBlockingQueue(): bool
    {
        return $this->blockingQueue;
    }

    /**
     * @param boolean $blockingQueue
     */
    public function setBlockingQueue(bool $blockingQueue)
    {
        $this->blockingQueue = $blockingQueue;
    }

    /**
     * @return mixed
     */
    public function connect()
    {
        $this->redis->open();
    }

    /**
     * @return mixed
     */
    public function disconnect()
    {
        $this->redis->close();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return 'redis';
    }

    public function prepareMultiThreaded()
    {
        $this->prefetchCount = 10;
    }

    /**
     * @param string $queueName
     * @param bool   $wait
     *
     * @return mixed
     */
    public function handleJobs(string $queueName, bool $wait = true)
    {
        $this->onBeforePop();
        $collectedJobs = 0;
        while ($wait) {
            while (($pop = $this->pop($queueName)) === null) {
                usleep(400000);
                $this->dispatchSystemEvents();
                if (!$wait) {
                    return;
                }
            }

            /** @var RedisPayload $payload */
            $payload = RedisPayload::createFromMessage(Json::decode($pop));
            $job     = Job::create($payload);
            try {
                $job->run();
            } catch (\Throwable $e) {
                if (!QueueComponent::find()->deleteOnError) {
                    $this->push($payload);
                }

                QueueComponent::find()->getLogger()->error($e);
            } finally {
                $this->onAfterPop(clone $job);
                unset($pop, $payload, $job);
                $collectedJobs++;
            }

            if ($collectedJobs === $this->prefetchCount) {
                usleep(500000);
                $collectedJobs = 0;
            }
            $this->dispatchSystemEvents();
        }
    }

    /**
     * @param RedisPayload $payload
     *
     * @return void
     */
    public function push($payload)
    {
        $this->redis->open();
        $this->redis->rpush($payload->getQueue(), $payload->getMessage());
    }

    /**
     * @param Payload[] $payloads
     *
     * @return mixed
     */
    public function pushBatch(array $payloads)
    {
        $iterationLimit = 10;
        $queue          = [];
        foreach ($payloads AS $payload) {
            $queue[$payload->getQueue()][] = $payload;
        }

        foreach ($queue AS $queueName => $pls) {
            if (sizeof($pls) <= $iterationLimit) {
                $messages = [];
                ArrayHelper::pushAllCallback($pls, $messages, function ($k, Payload $v) {
                    return $v->getMessage();
                });
                $this->redis->executeCommand('rpush', [$queueName, implode(' ', $messages)]);
            } else {
                $messages  = [];
                $collected = 0;

                while (sizeof($pls) > 0) {
                    while ($collected < $iterationLimit || sizeof($pls) < $iterationLimit) {
                        /** @var Payload $pl */
                        $pl = array_pop($pls);
                        if ($pl === null) {
                            break;
                        }
                        $messages[] = $pl->getMessage();
                        $collected++;
                    }

                    $this->redis->executeCommand('rpush', [$queueName, implode(' ', $messages)]);
                    $messages  = [];
                    $collected = 0;
                }
            }
        }
    }

    /**
     * @param string $queueName
     *
     * @return string|null
     */
    private function pop(string $queueName)
    {
        if ($this->blockingQueue) {
            return $this->redis->blpop($queueName);
        }

        return $this->redis->lpop($queueName);
    }
}