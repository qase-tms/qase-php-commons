<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models\FileReporter;

class Run
{
    public array $hostData = [];
    public string $title;
    public RunExecution $execution;
    public RunStats $stats;
    public array $results = [];
    public array $threads = [];
    public array $suites = [];
    public ?string $environment;

    public function __construct(string $title, int $startTime, int $endTime)
    {
        $this->title = $title;
        $this->execution = new RunExecution($startTime, $endTime);
        $this->stats = new RunStats();
    }

    public function addResults(array $results): void
    {
        foreach ($results as $result) {
            $shortResult = new ShortResult();
            $shortResult->id = $result->id;
            $shortResult->title = $result->title;
            $shortResult->status = $result->execution->status;
            $shortResult->duration = $result->execution->duration;
            $shortResult->thread = $result->execution->thread;

            $this->results[] = $shortResult;

            $this->execution->track($shortResult);
            $this->stats->track($shortResult);

            if (!in_array($shortResult->thread, $this->threads)) {
                $this->threads[] = $shortResult->thread;
            }
        }
    }
}
