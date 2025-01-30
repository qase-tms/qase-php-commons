<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class StepExecution
{
    protected ?int $startTime;
    protected ?int $endTime;
    protected ?int $duration;
    protected string $status;

    public function __construct(
        ?string $status = null
    )
    {
        $this->startTime = (int)(microtime(true) * 1000);
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

    public function setStartTime(int $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getStartTime(): ?int
    {
        return $this->startTime;
    }

    public function setEndTime(int $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function getEndTime(): ?int
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
        $this->endTime = (int)(microtime(true) * 1000);
        $this->duration = $this->endTime - $this->startTime;
    }
}
