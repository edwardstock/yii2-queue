<?php

namespace edwardstock\queue\statistics;

use edwardstock\queue\io\socket\SocketMessageExchange;
use edwardstock\queue\Queue;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
abstract class BaseStatManager
{
    /**
     * @var int
     */
    protected $flushInterval = 20;
    /**
     * @var array
     */
    protected $data = [];
    /**
     * @var int
     */
    protected $currentTime = 0;

    /**
     * @return string Manager ID
     */
    abstract public function getId(): string;

    /**
     * Attaches event handlers
     *
     * @param Queue $component
     */
    abstract public function attachEventHandlers(Queue $component);

    /**
     * Detaches event handlers
     * @return void
     */
    abstract public function detachEventHandlers();

    /**
     * Aggregates all written data to one array
     * @return array
     */
    abstract public function aggregate(): array;

    /**
     *
     */
    public function flush()
    {
        $this->beforeFlush();

        fwrite(STDOUT, "Flushing stats\n");
        $data       = $this->aggregate();
        $this->data = [];
        $message    = $this->formatMessage($data);

        $file = "/tmp/stat.log";
        $h    = fopen($file, 'w+');
        if (!$h) {
            throw new \RuntimeException('Cannot open log file');
        }
        fwrite($h, $message);
        fclose($h);

        $this->afterFlush();
    }

    /**
     * Must be called before flush
     */
    protected function beforeFlush()
    {
        if ($this->currentTime > time()) {
            return;
        }
    }

    /**
     * Must be called after flush
     */
    protected function afterFlush()
    {
        $this->currentTime = time() + $this->flushInterval;
    }

    protected function formatMessage(array $data): string
    {
        return serialize($data);
    }

    /**
     * @param string $key
     * @param        $value
     */
    protected function add(string $key, $value)
    {
        $this->data[$this->getId()][$key][] = $value;
    }

    /**
     * @param string $key
     * @param        $value
     */
    protected function set(string $key, $value)
    {
        $this->data[$this->getId()][$key] = $value;
    }

    /**
     * @param string $key
     * @param null   $defaultValue
     *
     * @return mixed|null
     */
    protected function get(string $key, $defaultValue = null)
    {
        return $this->data[$this->getId()][$key] ?? $defaultValue;
    }
}