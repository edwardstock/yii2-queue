<?php

namespace unit\drivers\rabbitmq\fakers;

use edwardstock\queue\drivers\Payload;
use edwardstock\queue\Worker;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class FakerJob implements Worker
{

    /**
     * Job handler method.
     *
     * @param Payload    $job
     * @param array|null $data
     */
    public function run($job, $data)
    {

    }
}