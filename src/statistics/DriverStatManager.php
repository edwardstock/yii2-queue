<?php

namespace edwardstock\queue\statistics;

use edwardstock\queue\drivers\Driver;
use edwardstock\queue\events\DriverEvent;
use edwardstock\queue\io\socket\SocketMessageExchange;
use edwardstock\queue\Queue;
use EdwardStock\Spork\SharedMemory;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class DriverStatManager extends BaseStatManager
{

    private $execStartTime = 0;
    /**
     * @var Queue
     */
    private $component;

    /**
     * @var SocketMessageExchange
     */
    private $exchange;

    public function __construct()
    {
        $this->exchange    = new SocketMessageExchange();
        $this->currentTime = time() + $this->flushInterval;
    }


    public function beforePopHandler(DriverEvent $event)
    {
        $this->execStartTime = microtime(true);
    }

    public function afterPopHandler(DriverEvent $event)
    {
        $this->add('pop', [
            'job'      => $event->getJob(),
            'time'     => time(),
            'execTime' => microtime(true) - $this->execStartTime,
        ]);
        $this->execStartTime = 0;

        $this->flush();
    }

    public function afterPushHandler(DriverEvent $event)
    {
        $this->add('push', [
            'payload' => $event->getPayload()->getMessage(),
            'time'    => time(),
        ]);
        $this->flush();
    }

    /**
     * Aggregates all wrote data to one statistics row
     */
    public function aggregate(): array
    {
        $data = [
            'executedMessage'  => 0,
            'sentMessages'     => 0,
            'avgSpeed'         => 0,
            'minExecutionTime' => 0,
            'maxExecutionTime' => 0,
        ];

        $data['sentMessages']    += sizeof($this->get('push', []));
        $data['executedMessage'] += sizeof($this->get('pop', []));

        $times     = [];
        $execTimes = [];
        foreach ($this->get('push', []) AS $pushInfo) {
            $times[] = (int)$pushInfo['time'];
        }

        foreach ($this->get('pop', []) AS $popInfo) {
            $times[]     = (int)$popInfo['time'];
            $execTimes[] = (int)$popInfo['execTime'];
        }

        sort($times);
        sort($execTimes);

        if (sizeof($execTimes) > 0) {
            $minTime                  = $execTimes[0];
            $maxTime                  = $execTimes[sizeof($execTimes) - 1];
            $data['minExecutionTime'] = (float)number_format($minTime * 1000, 3, '.', '');
            $data['maxExecutionTime'] = (float)number_format($maxTime * 1000, 3, '.', '');
        }

        if (sizeof($times) > 0) {
            $minTime          = $times[0];
            $maxTime          = $times[sizeof($times) - 1];
            $data['avgSpeed'] = (float)$data['executedMessage'] / ($maxTime - $minTime);
        }

        return $data;
    }

    /**
     * Attaches event handlers
     *
     * @param Queue $component
     */
    public function attachEventHandlers(Queue $component)
    {
        $this->component = $component;
        foreach ($component->getDrivers() AS $driver) {
            $driver->on(Driver::EVENT_AFTER_PUSH, [$this, 'afterPushHandler']);
            $driver->on(Driver::EVENT_BEFORE_POP, [$this, 'beforePopHandler']);
            $driver->on(Driver::EVENT_AFTER_POP, [$this, 'afterPopHandler']);
        }
    }

    /**
     * Detaches event handlers
     * @return void
     */
    public function detachEventHandlers()
    {
        foreach ($this->component->getDrivers() AS $driver) {
            $driver->off(Driver::EVENT_AFTER_PUSH, [$this, 'afterPushHandler']);
            $driver->off(Driver::EVENT_AFTER_POP, [$this, 'afterPopHandler']);
        }
    }

    public function flush()
    {
        if ($this->currentTime > time()) {
            return;
        }
        $message    = serialize($this->aggregate());
        $this->data = [];

        fwrite(STDOUT, "flusing stats\n");
        $this->exchange->send($message);

        $this->currentTime = time() + $this->flushInterval;
    }

    /**
     * @return string Manager ID
     */
    public function getId(): string
    {
        return 'driver';
    }
}