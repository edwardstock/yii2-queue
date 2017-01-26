<?php

namespace edwardstock\queue\drivers\redis;

use edwardstock\queue\drivers\Payload;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class RedisPayload extends Payload
{
    /**
     * @return string
     */
    public function getDriverId(): string
    {
        return 'redis';
    }

    /**
     * Дополнительные параметры которые будут отправлены в очередь
     * @return array
     */
    protected function getPayloadDriverParams(): array
    {
        return [];
    }
}