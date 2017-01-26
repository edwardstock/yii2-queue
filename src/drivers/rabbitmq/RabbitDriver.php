<?php
declare(ticks=1);

namespace edwardstock\queue\drivers\rabbitmq;

use edwardstock\queue\drivers\Driver;
use edwardstock\queue\drivers\Payload;
use edwardstock\queue\impl\yii\QueueComponent;
use edwardstock\queue\job\Job;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use yii\base\InvalidConfigException;
use yii\helpers\Json;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class RabbitDriver extends Driver
{
    const EX_TYPE_DIRECT  = 'direct';
    const EX_TYPE_FANOUT  = 'fanout';
    const EX_TYPE_HEADERS = 'headers';
    const EX_TYPE_TOPIC   = 'topic';

    public static $exchangeTypes = [
        self::EX_TYPE_DIRECT,
        self::EX_TYPE_FANOUT,
        self::EX_TYPE_TOPIC,
        self::EX_TYPE_HEADERS,
    ];

    /**
     * @var AbstractConnection|AMQPStreamConnection
     */
    private $connection = null;
    /**
     * @var AMQPChannel
     */
    private $channel = null;
    /**
     * @var string
     */
    private $connectionType = AMQPStreamConnection::class;
    /**
     * @var string
     */
    private $host = 'localhost';
    /**
     * @var int
     */
    private $port = 5672;
    /**
     * @var string
     */
    private $user = 'guest';
    /**
     * @var string
     */
    private $password = 'guest';
    /**
     * @var string
     */
    private $vhost = '/';

    /**
     * @var int
     */
    private $prefetchCount = 0;

    /**
     *
     */
    public function init()
    {
        parent::init();
        if ($this->isAutoConnect()) {
            $this->connect();
        }
    }

    public function __destruct()
    {
        if ($this->isAutoDisconnect()) {
            $this->disconnect();
        }
    }

    public function connect()
    {
        $className        = $this->connectionType;
        $this->connection = new $className(
            $this->host,
            $this->port,
            $this->user,
            $this->password,
            $this->vhost,
            $insist = false,
            $login_method = 'AMQPLAIN',
            $login_response = null,
            $locale = 'en_US',
            $connection_timeout = 3.0,
            $read_write_timeout = 3.0,
            $context = null,
            $keepalive = !$this->isAutoDisconnect(),
            $heartbeat = 0
        );

        $this->channel = $this->connection->channel();

        \Yii::info('Opening RabbitMQ connection', __CLASS__);
    }

    public function disconnect()
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            try {
                $this->channel->close();
                $this->connection->close();
            } catch (AMQPRuntimeException $ex) {

            }
        }
        \Yii::info('Close RabbitMQ connection', __CLASS__);
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        return $this->channel;
    }

    /**
     * @return AbstractConnection|AMQPStreamConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return 'rabbitmq';
    }

    /**
     * @return int
     */
    public function getPrefetchCount(): int
    {
        return $this->prefetchCount;
    }

    /**
     * @param int $prefetchCount
     */
    public function setPrefetchCount(int $prefetchCount)
    {
        $this->prefetchCount = $prefetchCount;
    }

    /**
     * @param string $queueName
     * @param bool   $wait
     *
     * @return mixed|void
     */
    public function handleJobs(string $queueName, bool $wait = true)
    {
        $channel = $this->getChannel();

        /** @var RabbitPayload $payload */
        $payload = QueueComponent::find()->waitForPayload($this->getId(), $queueName);

        $channel->queue_declare($queueName,
            $payload->queueIsPassive(),
            $payload->queueIsDurable(),
            $payload->queueIsExclusive(),
            $payload->queueIsAutoDeleting()
        );

        if ($this->getPrefetchCount() > 0) {
            $channel->basic_qos(null, $this->getPrefetchCount(), null);
        }

        $channel->basic_consume($queueName, '', false, false, false, false, [$this, 'onMessage']);

        while (count($channel->callbacks)) {
            $this->dispatchSystemEvents();
            if ($wait) {
                usleep(200000);
            }

        }
    }

    public function isConnected()
    {
        return $this->connection->isConnected();
    }

    public function onMessage(AMQPMessage $message)
    {
        $job = Job::create(RabbitPayload::createFromMessage(Json::decode($message->getBody())));
        $this->onBeforePop();

        $success = true;
        try {
            $job->run();
        } catch (\Throwable $e) {
            if (QueueComponent::find()->deleteOnError) {
                $this->ack($message);
            } else {
                $this->cancel($message);
            }

            QueueComponent::find()->getLogger()->error($e);
            $success = false;
        }

        if ($success) {
            $this->ack($message);
        }

        $this->dispatchSystemEvents();
        $this->onAfterPop(clone $job);
        unset($job);
    }

    /**
     *
     */
    public function prepareMultiThreaded()
    {
        if ($this->prefetchCount === 0) {
            $this->setPrefetchCount(3);
        }
    }

    /**
     * @param RabbitPayload $payload
     *
     * @return void
     */
    public function push($payload)
    {
        $this->onBeforePush($payload);
        $exchangeCreated = false;
        if (strpos($payload->getExchange(), 'amqp.') === false && $payload->getExchange() !== '') {
            $this->channel->exchange_declare(
                $payload->getExchange(),
                self::EX_TYPE_DIRECT,
                $payload->exchangeIsPassive(),
                $payload->exchangeIsDurable(),
                $payload->exchangeIsAutoDeleting()
            );
            $exchangeCreated = true;
        }
        $this->channel->queue_declare(
            $payload->getQueue(),
            $payload->queueIsPassive(),
            $payload->queueIsDurable(),
            $payload->queueIsExclusive(),
            $payload->queueIsAutoDeleting());

        if ($exchangeCreated) {
            $this->channel->queue_bind($payload->getQueue(), $payload->getExchange(), $payload->getQueue());
        }

        $msg = new AMQPMessage($payload->getMessage(), $payload->getMessageProperties());
        $this->channel->basic_publish($msg, $payload->getExchange(), $payload->getQueue());
        $this->onAfterPush($payload);
    }

    /**
     * @param Payload[] $payloads
     *
     * @return mixed
     */
    public function pushBatch(array $payloads)
    {
        $pushedCount = 0;
        foreach ($payloads AS $payload) {
            $this->onBeforePush($payload);
            $exchangeCreated = false;
            if (strpos($payload->getExchange(), 'amqp.') === false && $payload->getExchange() !== '') {
                $this->channel->exchange_declare(
                    $payload->getExchange(),
                    self::EX_TYPE_DIRECT,
                    $payload->exchangeIsPassive(),
                    $payload->exchangeIsDurable(),
                    $payload->exchangeIsAutoDeleting()
                );
                $exchangeCreated = true;
            }
            $this->channel->queue_declare(
                $payload->getQueue(),
                $payload->queueIsPassive(),
                $payload->queueIsDurable(),
                $payload->queueIsExclusive(),
                $payload->queueIsAutoDeleting());

            if ($exchangeCreated) {
                $this->channel->queue_bind($payload->getQueue(), $payload->getExchange(), $payload->getQueue());
            }

            $msg = new AMQPMessage($payload->getMessage(), $payload->getMessageProperties());
            $this->channel->batch_basic_publish($msg, $payload->getExchange(), $payload->getQueue());
            $this->onAfterPush($payload);
            if ($pushedCount > 0 && $pushedCount % 100 === 0) {
                $this->channel->publish_batch();
            }
        }

        $this->channel->publish_batch();
    }

    /**
     * @param string $connectionClass
     *
     * @throws InvalidConfigException
     */
    public function setConnectionType(string $connectionClass)
    {
        if (!class_exists($connectionClass)) {
            throw new InvalidConfigException('Connection class ' . $connectionClass . ' not found');
        }

        $this->connectionType = $connectionClass;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host)
    {
        $this->host = $host;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port)
    {
        $this->port = $port;
    }

    /**
     * @param string $user
     */
    public function setUser(string $user)
    {
        $this->user = $user;
    }

    /**
     * @param string $vhost
     */
    public function setVhost(string $vhost)
    {
        $this->vhost = $vhost;
    }

    /**
     * @param AMQPMessage $message
     */
    protected function ack(AMQPMessage $message)
    {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * @param AMQPMessage $message
     */
    protected function cancel(AMQPMessage $message)
    {
        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
    }
}