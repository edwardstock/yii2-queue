<?php
declare(ticks=1);

namespace edwardstock\queue;

use edwardstock\queue\job\ClassJob;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
interface Worker
{
    /**
     * Job handler method.
     *
     * @param ClassJob $job
     * @param array    $data
     */
    public function run($job, $data);
}