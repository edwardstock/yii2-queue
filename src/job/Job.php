<?php

namespace edwardstock\queue\job;

use edwardstock\queue\drivers\Payload;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
abstract class Job
{
    /**
     * @var Payload
     */
    protected $payload;

    /**
     * @param Payload $payload
     *
     * @return Job|CallbackJob|ClassJob|ShellJob
     */
    public static function create(Payload $payload)
    {
        if ($payload->isCallback()) {
            return new CallbackJob($payload);
        } else if ($payload->isShell()) {
            return new ShellJob($payload);
        } else {
            return new ClassJob($payload);
        }
    }

    /**
     * @return mixed
     * @throws \Throwable
     */
    abstract public function run();

    /**
     * Job constructor.
     *
     * @param Payload $payload
     */
    public function __construct(Payload $payload)
    {
        $this->payload = $payload;
    }

    /**
     * @return Payload
     */
    public function getPayload()
    {
        return $this->payload;
    }
}