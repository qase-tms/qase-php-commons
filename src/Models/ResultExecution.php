<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class ResultExecution
{
    public ?string $status;
    public ?float $startTime;
    public ?float $endTime;
    public ?int $duration;
    public ?string $stackTrace;
    public ?string $thread;

    public function __construct(
        ?string $status = null
    )
    {
        $this->status = $status;
        $this->startTime = microtime(true);
        $this->endTime = null;
        $this->duration = null;
        $this->stackTrace = null;
        $this->thread = null;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getStatus(): ?string
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

    public function setStackTrace(string $stackTrace): void
    {
        if ($this->stackTrace === null) {
            $this->stackTrace = $stackTrace;
            return;
        }

        $this->stackTrace .= "\n" . $stackTrace;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setThread(string $thread): void
    {
        $this->thread = $thread;
    }

    public function getThread(): ?string
    {
        return $this->thread;
    }

    public function finish(): void
    {
        $this->endTime = microtime(true);
        $this->duration = (int)($this->endTime - $this->startTime);
    }
}
