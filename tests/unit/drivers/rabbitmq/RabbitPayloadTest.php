<?php


use edwardstock\queue\drivers\rabbitmq\RabbitPayload;
use unit\drivers\rabbitmq\fakers\FakerJob;

class RabbitPayloadTest extends \PHPUnit_Framework_TestCase
{

    public function testSetExchange()
    {
        $payload = $this->create();
        $this->assertEquals('', $payload->getExchange());

        $payload->setExchange('yii2:jobs');
        $this->assertEquals('yii2:jobs', $payload->getExchange());
    }

    public function testSetQueueName()
    {
        $newName = 'something';
        $payload = $this->create();
        $prefix  = $payload->getQueuePrefix();

        $this->assertEquals($prefix . ':default', $payload->getQueue());
        $payload->setQueue($newName);

        $this->assertNotEquals($newName, $payload->getQueue());
        $this->assertEquals($prefix . ':' . $newName, $payload->getQueue());
    }

    private function create(array $data = [])
    {
        return new RabbitPayload(FakerJob::class, $data);
    }

}
