<?php

namespace edwardstock\queue\config;

use edwardstock\queue\exceptions\InvalidConfigException;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class ServiceConfig
{

    /**
     * @var ServiceConfig
     */
    private static $instance;

    private $config;
    private $file;

    private $drivers = [];

    /**
     * @param string|null $fileName
     *
     * @return ServiceConfig
     */
    public static function reload(string $fileName = null): ServiceConfig
    {
        $configFile = $fileName;
        if (self::$instance instanceof ServiceConfig) {
            $configFile     = $fileName ?? self::$instance->file;
            self::$instance = null;
        }

        return self::load($configFile);
    }

    /**
     * @param string $fileName
     *
     * @return ServiceConfig
     */
    public static function load(string $fileName): ServiceConfig
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $instance       = new ServiceConfig($fileName);
        self::$instance = $instance;

        return $instance;
    }

    /**
     * ServiceConfig constructor.
     *
     * @param string $fileName
     *
     * @throws InvalidConfigException
     * @throws \RuntimeException
     */
    private function __construct(string $fileName)
    {
        if (!file_exists($fileName) || !is_readable($fileName)) {
            throw new \RuntimeException("Cannot read config file {$fileName}");
        }
        $this->file = $fileName;

        $this->config = parse_ini_file($fileName, true, INI_SCANNER_TYPED);
        foreach ($this->config AS $driverName => $params) {
            if (strpos($driverName, 'driver:') === false) {
                throw new InvalidConfigException('Driver name must defined like this: [driver:redis] or [driver:rabbitmq]');
            }

            $tmp   = explode(':', $driverName);
            $dName = end($tmp);

            foreach ($params AS $queueName => $workers) {
                $this->drivers[$dName][] = [
                    'driver'  => $dName,
                    'queue'   => $queueName,
                    'workers' => $workers,
                ];
            }
        }
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return $this->drivers;
    }

    /**
     * @param string $driver
     *
     * @return array
     */
    public function getQueuesByDriver(string $driver): array
    {
        return $this->drivers[$driver] ?? [];
    }
}