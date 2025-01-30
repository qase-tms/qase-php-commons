<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Loggers;

use Qase\PhpCommons\Interfaces\LoggerInterface;

class Logger implements LoggerInterface
{
    private const PREFIX = '[Qase]';
    private const LEVEL_COLORS = [
        'INFO' => "\033[32m",  // Green
        'DEBUG' => "\033[36m", // Blue
        'ERROR' => "\033[31m", // Red
        'RESET' => "\033[0m",  // Reset color
    ];
    private bool $debug;
    private string $logFilePath;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
        $this->logFilePath = getcwd() . '/logs/log_' . date('Y-m-d') . '.log';

        if (!is_dir(getcwd() . '/logs')) {
            mkdir(getcwd() . '/logs', 0777, true);
        }
    }

    public function info(string $message): void
    {
        $this->log($message, 'INFO');
    }

    public function debug(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        $this->log($message, 'DEBUG');
    }

    public function error(string $message): void
    {
        $this->log($message, 'ERROR');
    }

    private function log(string $message, string $level): void
    {
        $color = self::LEVEL_COLORS[$level] ?? self::LEVEL_COLORS['RESET'];
        $reset = self::LEVEL_COLORS['RESET'];

        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = sprintf("%s %s%s %s %s%s\n", $color, self::PREFIX, $timestamp, $level, $message, $reset);

        echo $formattedMessage;

        file_put_contents($this->logFilePath, $timestamp . ' ' . $level . ' ' . $message . PHP_EOL, FILE_APPEND);
    }
}
