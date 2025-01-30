<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Interfaces;

use Qase\PhpCommons\Models\Result;

interface ReporterInterface
{
    public function startRun(): void;

    public function completeRun(): void;

    public function sendResults(): void;

    public function addResult(Result $result): void;
}
