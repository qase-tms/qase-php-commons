<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class StepData
{
    public ?string $action;
    public ?string $expectedResult;

    public function __construct(
        ?string $action = null,
        ?string $expectedResult = null
    )
    {
        $this->action = $action;
        $this->expectedResult = $expectedResult;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setExpectedResult(?string $expectedResult): void
    {
        $this->expectedResult = $expectedResult;
    }

    public function getExpectedResult(): ?string
    {
        return $this->expectedResult;
    }
}
