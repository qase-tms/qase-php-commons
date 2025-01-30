<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Interfaces;

interface LoggerInterface
{
    public function info(string $message): void;
    public function debug(string $message): void;
    public function error(string $message): void;
}
