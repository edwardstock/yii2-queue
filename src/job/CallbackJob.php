<?php

namespace edwardstock\queue\job;

use edwardstock\queue\drivers\Payload;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class CallbackJob extends Job
{
    public function __construct(Payload $payload)
    {
        parent::__construct($payload);
    }

    /**
     * @return mixed
     * @throws \Throwable
     */
    public function run()
    {
        $func = $this->payload->getJob();

        return $func(...$this->payload->getData());
    }
}