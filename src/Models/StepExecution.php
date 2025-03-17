<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class StepExecution
{
    protected ?float $startTime = null;
    protected ?float $endTime = null;
    protected ?int $duration = null;
    protected string $status;

    public function __construct(
        string $status = 'untested'
    )
    {
        $this->startTime = microtime(true);
        $this->setStatus($status);
    }

    public function setStatus(?string $status): void
    {
        $validStatuses = ['passed', 'failed', 'skipped', 'blocked', 'untested'];

        if (in_array($status, $validStatuses, true)) {
            $this->status = $status;
        } else {
            throw new \InvalidArgumentException(
                'Step status must be one of: ' . implode(', ', $validStatuses)
            );
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStartTime(float $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getStartTime(): ?float
    {
        return $this->startTime;
    }

    public function setEndTime(float $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    public function setDuration(int $duration): void
    {
        $this->duration = $duration;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function finish(): void
    {
        $this->endTime = microtime(true);
        $this->duration = (int)($this->endTime - $this->startTime);
    }
}
