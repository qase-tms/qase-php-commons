<?php

namespace Qase\PhpCommons\Models\FileReporter;

class RunExecution
{
    public int $startTime;
    public int $endTime;
    public int $duration;
    public int $cumulativeDuration;

    public function __construct(int $startTime, int $endTime)
    {
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->duration = 0;
        $this->cumulativeDuration = 0;
    }

    public function track(ShortResult $result): void
    {
        $this->cumulativeDuration += $result->duration;
    }

    public function countDuration(): void
    {
        $this->duration = $this->endTime - $this->startTime;
    }
}
