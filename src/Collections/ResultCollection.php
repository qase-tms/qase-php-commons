<?php

declare (strict_types=1);

namespace Qase\PhpCommons\Collections;

use Qase\PhpCommons\Models\Result;

class ResultCollection
{
    private array $results = [];

    public function addResult(Result $result)
    {
        $this->results[] = $result;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getCount(): int
    {
        return count($this->results);
    }
}