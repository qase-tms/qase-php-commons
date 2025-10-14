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
        'WARNING' => "\033[33m", // Yellow
        'RESET' => "\033[0m",  // Reset color
    ];
    private bool $debug;
    private bool $consoleEnabled;
    private bool $fileEnabled;
    private string $logFilePath;

    public function __construct(bool $debug = false, ?array $loggingConfig = null)
    {
        $this->debug = $debug;
        $this->logFilePath = getcwd() . '/logs/log_' . date('Y-m-d') . '.log';

        // Set default logging configuration
        $this->consoleEnabled = true;
        $this->fileEnabled = true;

        // Override with config if provided
        if ($loggingConfig !== null) {
            if (isset($loggingConfig['console'])) {
                $this->consoleEnabled = (bool)$loggingConfig['console'];
            }
            if (isset($loggingConfig['file'])) {
                $this->fileEnabled = (bool)$loggingConfig['file'];
            }
        }

        // Create logs directory if file logging is enabled
        if ($this->fileEnabled && !is_dir(getcwd() . '/logs')) {
            mkdir(getcwd() . '/logs', 0777, true);
        }
    }

    public function info(string $message): void
    {
        $this->writeLog($message, 'INFO');
    }

    public function debug(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        $this->writeLog($message, 'DEBUG');
    }

    public function error(string $message): void
    {
        $this->writeLog($message, 'ERROR');
    }

    public function warning(string $message): void
    {
        $this->writeLog($message, 'WARNING');
    }

    private function writeLog(string $message, string $level): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = $timestamp . ' ' . $level . ' ' . $message . PHP_EOL;

        // Console output
        if ($this->consoleEnabled) {
            $color = self::LEVEL_COLORS[$level] ?? self::LEVEL_COLORS['RESET'];
            $reset = self::LEVEL_COLORS['RESET'];
            $formattedMessage = sprintf("%s %s%s %s %s%s\n", $color, self::PREFIX, $timestamp, $level, $message, $reset);
            echo $formattedMessage;
        }

        // File output
        if ($this->fileEnabled) {
            file_put_contents($this->logFilePath, $logEntry, FILE_APPEND);
        }
    }
}
