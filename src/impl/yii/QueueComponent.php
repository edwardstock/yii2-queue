<?php

namespace edwardstock\queue\impl\yii;

use edwardstock\queue\Queue;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * yii2-queue. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class QueueComponent extends Component
{
    /**
     * @var Queue|null
     */
    private static $component = null;

    /**
     * @return Queue
     * @throws InvalidConfigException
     */
    public static function find(): Queue
    {
        if (self::$component instanceof Queue) {
            return self::$component;
        }

        foreach (\Yii::$app->components AS $name => $params) {
            if ($params['class'] === Queue::class) {
                self::$component = \Yii::$app->get($name);

                return self::$component;
            }
        }

        throw new InvalidConfigException('Queue component does not configured in main config file. Check it');
    }

    /**
     * @return string
     */
    public static function getShortClassName(): string
    {
        return (new \ReflectionClass(get_called_class()))->getShortName();
    }
}