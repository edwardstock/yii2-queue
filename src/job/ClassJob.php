<?php

namespace edwardstock\queue\job;

use edwardstock\queue\drivers\Payload;
use edwardstock\queue\exceptions\QueueRuntimeException;
use edwardstock\queue\Worker;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class ClassJob extends Job
{
    /**
     * @var Worker
     */
    private $worker;

    /**
     * Job constructor.
     *
     * @param Payload $payload
     *
     * @throws QueueRuntimeException
     */
    public function __construct(Payload $payload)
    {
        parent::__construct($payload);
        if (!class_exists($payload->getJob())) {
            throw new QueueRuntimeException('Worker class ' . $payload->getJob() . ' not found!');
        }

        $class        = $payload->getJob();
        $this->worker = new $class();
        if (!($this->worker instanceof Worker)) {
            throw new QueueRuntimeException('Worker class must implements ' . Worker::class);
        }

    }

    /**
     * @return mixed
     * @throws \Throwable
     */
    public function run()
    {
        $this->worker->run($this, $this->getPayload()->getData());
    }
}