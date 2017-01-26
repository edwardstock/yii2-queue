<?php

namespace edwardstock\queue\job;

use edwardstock\queue\drivers\Payload;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class ShellJob extends Job
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
        // TODO: Implement run() method.
    }
}