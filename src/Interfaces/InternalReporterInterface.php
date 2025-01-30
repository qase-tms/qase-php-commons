<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Interfaces;


interface InternalReporterInterface extends ReporterInterface
{
    public function getResults(): array;

    public function setResults(array $results): void;
}
