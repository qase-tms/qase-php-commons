<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class ResultExecution
{
    public ?string $status;
    public ?int $startTime;
    public ?int $endTime;
    public ?int $duration;
    public ?string $stackTrace;
    public ?string $thread;

    public function __construct(
        ?string $status = null
    )
    {
        $this->status = $status;
        $this->startTime = (int)(microtime(true) * 1000);
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
        $this->endTime = (int)(microtime(true) * 1000);
        $this->duration = $this->endTime - $this->startTime;
    }
}
