<?php

namespace edwardstock\queue\impl\log;

use edwardstock\queue\Queue;
use yii\helpers\Console;
use yii\log\Logger;

/**
 * log_request. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
class CliLogger
{
    /**
     * Profiling stores are here
     * @var array
     */
    private $profiles = [];

    private $serviceLogFileError = '/var/log/yii2queue.err';
    private $serviceLogFileInfo  = '/var/log/yii2queue.log';
    private $osLogFiles          = [
        'error' => null,
        'info'  => null,
    ];
    private $debug               = true;
    private $prefix              = 'Queue: ';

    public function __construct(bool $debug, string $prefix = null)
    {
        $this->debug = $debug;
        if ($prefix !== null) {
            $this->prefix = $prefix;
        }
    }

    public function __destruct()
    {
        foreach ($this->osLogFiles AS &$handle) {
            if ($handle !== null) {
                @fclose($handle);
            }
        }
    }

    /**
     * @param mixed $msg
     */
    public function error($msg)
    {
        $this->write($msg, Logger::LEVEL_ERROR);
    }

    /**
     * @param mixed $msg
     */
    public function info($msg)
    {
        $this->write($msg, Logger::LEVEL_INFO);
    }

    /**
     * @param mixed $msg
     */
    public function warning($msg)
    {
        $this->write($msg, Logger::LEVEL_WARNING);
    }

    /**
     * @param string $name
     */
    public function profileStart(string $name)
    {
        if (!$this->debug) {
            return;
        }

        $hash                  = crc32($name);
        $this->profiles[$hash] = microtime(true);
        \Yii::beginProfile($name, Queue::class);
    }

    /**
     * @param string $name
     */
    public function profileEnd(string $name)
    {
        $hash = crc32($name);
        if (!isset($this->profiles[$hash])) {
            echo "Profile {$name} not found\n";

            return;
        }
        \Yii::endProfile($name, Queue::class);
        $res = $this->profiles[$hash] = microtime(true) - $this->profiles[$hash];
        if (!$this->debug) {
            return;
        }

        fwrite(STDOUT, "[Profiling] {$name} " . ($res * 1000) . " ms\n");
    }

    /**
     * @param $msg
     *
     * @return mixed|null|string
     */
    private function prepareMessage($msg)
    {
        $preparedMessage = $this->prefix;
        if (is_string($msg)) {
            $preparedMessage .= $msg;
        } else {
            if ($msg instanceof \Throwable) {
                $preparedMessage = '[' . $msg->getCode() . ']' . $msg->getMessage() . PHP_EOL;
                $preparedMessage .= $msg->getTraceAsString();
            } else {
                $preparedMessage = var_export($msg, true);
            }
        }

        return $preparedMessage;
    }

    /**
     * @param     $msg
     * @param int $level
     */
    private function write($msg, int $level = Logger::LEVEL_INFO)
    {
        if (!$this->debug) {
            return;
        }

        $msg = $this->prepareMessage($msg);

        $this->writeCli($msg, $level === Logger::LEVEL_ERROR);
        $this->writeOsLog($msg, $level === Logger::LEVEL_ERROR);
        $this->writeSystem($msg, $level);
    }

    /**
     * @param string $msg
     * @param bool   $error
     */
    private function writeCli(string $msg, bool $error = false)
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $color  = Console::streamSupportsAnsiColors(\STDOUT);
        $string = $msg;
        if ($color && $error) {
            $string = Console::ansiFormat($string, [Console::BG_RED]);
        }

        $string .= PHP_EOL;

        fwrite($error ? \STDERR : \STDOUT, $string);
    }

    private function writeOsLog(string $msg, bool $error = false)
    {
        $fileHandle = function (bool $error) {
            $handle = &$this->osLogFiles[$error ? 'error' : 'info'];
            if ($handle === null) {
                $handle = fopen($error ? $this->serviceLogFileError : $this->serviceLogFileInfo, 'a+');
            }

            return $handle;
        };

        $ts = "[" . date('Y-m-d H:i:s') . "] ";
        @fwrite($fileHandle($error), $ts . $msg . PHP_EOL);
    }

    /**
     * @param string $msg
     * @param int    $level
     */
    private function writeSystem(string $msg, int $level)
    {
        \Yii::getLogger()->log($msg, $level, Queue::class);
    }
}