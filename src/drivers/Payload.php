<?php
/**
 * yii2-queue. 2016
 * Date: 05.07.16
 * Time: 14:40
 */

namespace edwardstock\queue\drivers;

use edwardstock\forker\helpers\Serializer;
use Symfony\Component\Process\Process;
use yii\helpers\Json;

abstract class Payload
{
    const IS_CLASS    = (1 << 0);
    const IS_CALLBACK = (1 << 1);
    const IS_SHELL    = (1 << 2);

    /**
     * @var string
     */
    protected $job;

    /**
     * @var int Probably in future, this will used as mixed payload params
     */
    protected $flags = self::IS_CLASS;

    /**
     * @var string
     */
    protected $queue = 'yii2queue:default';

    /**
     * @var mixed[]
     */
    protected $data = [];

    /**
     * @param array $message Decoded payload data
     *
     * @return static
     */
    public static function createFromMessage(array $message)
    {
        $message['data'] = Serializer::unserialize($message['data']);
        $payload         = new static(null, $message['queue'], ...$message['data']);
        foreach ($message AS $k => $v) {
            if (property_exists($payload, $k)) {
                $payload->{$k} = $v;
            }
        }

        return $payload;
    }

    /**
     * @return string
     */
    abstract public function getDriverId(): string;

    /**
     * Дополнительные параметры которые будут отправлены в очередь
     * @return array
     */
    abstract protected function getPayloadDriverParams(): array;

    /**
     * Payload constructor.
     *
     * @param string|\Closure|Process $job
     * @param string                  $queue
     * @param mixed                   $arguments
     */
    public function __construct($job, string $queue = 'default', ...$arguments)
    {
        $this->setJob($job);
        $this->setQueue($queue);
        if (!is_array($arguments)) {
            $this->data[] = null;
        } else {
            $this->data = $arguments;
        }
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setData(...$data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return string|\Closure|Process
     */
    public function getJob()
    {
        if ($this->isCallback()) {
            return Serializer::unserialize($this->job);
        } else if ($this->isShell()) {
            return unserialize($this->job);
        }

        return $this->job;
    }

    /**
     * @param string $job Class name
     *
     * @return $this
     */
    public function setJob($job)
    {
        if (is_callable($job)) {
            $this->job   = Serializer::serialize($job);
            $this->flags = self::IS_CALLBACK;
        } else if ($job instanceof Process) {
            $this->job   = serialize($job);
            $this->flags = self::IS_SHELL;
        } else {
            $this->job   = $job;
            $this->flags = self::IS_CLASS;
        }

        return $this;
    }

    /**
     * @return string Json object
     */
    public function getMessage(): string
    {
        return Json::encode(array_merge($this->getMessageData(), $this->getPayloadDriverParams()));
    }

    /**
     * @return array
     */
    public function getMessageData(): array
    {
        return [
            'driver' => $this->getDriverId(),
            'queue'  => $this->getQueue(),
            'flags'  => $this->flags,
            'job'    => $this->job,
            'data'   => Serializer::serialize($this->data),
        ];
    }

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * @param string $queue
     *
     * @return $this
     */
    public function setQueue(string $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCallback(): bool
    {
        return $this->hasFlag(self::IS_CALLBACK);
    }

    /**
     * @return bool
     */
    public function isClassHandler(): bool
    {
        return $this->hasFlag(self::IS_CLASS);
    }

    /**
     * @return int
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * @return bool
     */
    public function isShell(): bool
    {
        return $this->hasFlag(self::IS_SHELL);
    }

    /**
     * @param int $flag
     *
     * @return bool
     */
    protected function hasFlag(int $flag)
    {
        return ($this->flags & $flag) === $flag;
    }
}