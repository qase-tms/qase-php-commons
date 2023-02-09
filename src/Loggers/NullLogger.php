<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Loggers;
use Qase\PhpCommons\Interfaces\LoggerInterface;

class NullLogger implements LoggerInterface
{
    public function write(string $message, string $prefix = '[Qase reporter]'): void
    {
    }

    public function writeln(string $message, string $prefix = '[Qase reporter]'): void
    {
    }
}
