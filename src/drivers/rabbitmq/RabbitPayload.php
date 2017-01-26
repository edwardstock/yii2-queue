<?php

namespace edwardstock\queue\drivers\rabbitmq;

use edwardstock\queue\drivers\Payload;
use Symfony\Component\Process\Process;
use yii\base\InvalidConfigException;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class RabbitPayload extends Payload
{
    /**
     * @var array
     */
    protected $messageProperties = [
        'delivery_mode' => 2, //persistent
    ];

    /**
     * @var string
     */
    protected $exchange = 'yii2:jobs';

    /**
     * @var string
     */
    protected $exchangeType = 'direct';

    /**
     * @var bool
     */
    protected $exchangeIsPassive = false;

    /**
     * @var bool
     */
    protected $exchangeIsDurable = true;

    /**
     * @var bool
     */
    protected $autoDeleteExchange = false;

    /**
     * @var bool
     */
    protected $queueIsPassive = false;

    /**
     * @var bool
     */
    protected $queueIsDurable = true;

    /**
     * @var bool
     */
    protected $autoDeleteQueue = false;

    /**
     * @var bool
     */
    protected $queueIsExclusive = false;

    /**
     * @param $job
     *
     * @return RabbitPayload
     */
    public static function create($job)
    {
        return new RabbitPayload($job);
    }

    /**
     * RabbitPayload constructor.
     *
     * @param \Closure|string|Process $job
     * @param string                  $queue    null - default queue
     * @param string                  $exchange null - default exchange
     * @param array                   ...$data
     */
    public function __construct($job, string $queue = null, string $exchange = null, ...$data)
    {
        parent::__construct($job, $queue ?? $this->queue, ...$data);
        $this->setExchange($exchange ?? $this->exchange);
    }

    /**
     * @return bool
     */
    public function exchangeIsAutoDeleting(): bool
    {
        return $this->autoDeleteExchange;
    }

    /**
     * @return bool
     */
    public function exchangeIsDurable(): bool
    {
        return $this->exchangeIsDurable;
    }

    /**
     * @return bool
     */
    public function exchangeIsPassive(): bool
    {
        return $this->exchangeIsPassive;
    }

    /**
     * @return string
     */
    public function getExchange(): string
    {
        return $this->exchange;
    }

    /**
     * @param string $exchange
     *
     * @return $this
     */
    public function setExchange(string $exchange)
    {
        $this->exchange = $exchange;

        return $this;
    }

    /**
     * @return string
     */
    public function getExchangeType(): string
    {
        return $this->exchangeType;
    }

    /**
     * @see RabbitDriver::$exchangeTypes
     *
     * @param string $type
     *
     * @return $this
     * @throws InvalidConfigException
     */
    public function setExchangeType(string $type)
    {
        if (!in_array($type, RabbitDriver::$exchangeTypes)) {
            throw new InvalidConfigException('Unknown exchange type');
        }

        $this->exchangeType = $type;

        return $this;
    }

    /**
     * @return array
     */
    public function getMessageProperties(): array
    {
        return $this->messageProperties;
    }

    /**
     * @param array $props
     *
     * @return $this
     */
    public function setMessageProperties(array $props)
    {
        $this->messageProperties = $props;

        return $this;
    }

    /**
     * @return bool
     */
    public function queueIsAutoDeleting(): bool
    {
        return $this->autoDeleteQueue;
    }

    /**
     * @return bool
     */
    public function queueIsDurable(): bool
    {
        return $this->queueIsDurable;
    }

    /**
     * @return bool
     */
    public function queueIsExclusive(): bool
    {
        return $this->queueIsExclusive;
    }

    /**
     * @return bool
     */
    public function queueIsPassive(): bool
    {
        return $this->queueIsPassive;
    }

    /**
     * @param bool $autoDelete
     *
     * @return $this
     */
    public function setAutoDeleteExchange(bool $autoDelete = false)
    {
        $this->autoDeleteExchange = $autoDelete;

        return $this;
    }

    /**
     * @param bool $autoDelete
     *
     * @return $this
     */
    public function setAutoDeleteQueue(bool $autoDelete = false)
    {
        $this->autoDeleteQueue = $autoDelete;

        return $this;
    }

    /**
     * @param bool $durable
     *
     * @return $this
     */
    public function setDurableExchange(bool $durable = true)
    {
        $this->exchangeIsDurable = $durable;

        return $this;
    }

    /**
     * @param string $key
     * @param        $value
     *
     * @return $this
     */
    public function setMessageProperty(string $key, $value)
    {
        $this->messageProperties[$key] = $value;

        return $this;
    }

    /**
     * @param bool $passive
     *
     * @return $this
     */
    public function setPassiveExchange(bool $passive = false)
    {
        $this->exchangeIsPassive = $passive;

        return $this;
    }

    /**
     * @param bool $passive
     *
     * @return $this
     */
    public function setPassiveQueue(bool $passive = false)
    {
        $this->queueIsPassive = $passive;

        return $this;
    }

    /**
     * @param bool $durable
     *
     * @return $this
     */
    public function setQueueDurable(bool $durable = true)
    {
        $this->queueIsDurable = $durable;

        return $this;
    }

    /**
     * @param bool $exclusive
     *
     * @return $this
     */
    public function setQueueExclusive(bool $exclusive = false)
    {
        $this->queueIsExclusive = $exclusive;

        return $this;
    }

    /**
     * @return string
     */
    public function getDriverId(): string
    {
        return 'rabbitmq';
    }

    /**
     * Дополнительные параметры которые будут отправлены в очередь
     * @return array
     */
    protected function getPayloadDriverParams(): array
    {
        return [
            'exchange'     => $this->getExchange(),
            'exchangeType' => $this->getExchangeType(),
        ];
    }
}