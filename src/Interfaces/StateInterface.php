<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Interfaces;

interface StateInterface
{
    public function startRun(callable $createRun): int;

    public function completeRun(callable $completeRun): void;
}
