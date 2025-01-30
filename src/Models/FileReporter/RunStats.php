<?php

namespace Qase\PhpCommons\Models\FileReporter;

class RunStats
{
    public int $passed;
    public int $failed;
    public int $skipped;
    public int $broken;
    public int $muted;
    public int $total;

    public function __construct()
    {
        $this->passed = 0;
        $this->failed = 0;
        $this->skipped = 0;
        $this->broken = 0;
        $this->muted = 0;
        $this->total = 0;
    }

    public function track(ShortResult $result): void
    {
        $this->total++;
        switch ($result->status) {
            case 'passed':
                $this->passed++;
                break;
            case 'failed':
                $this->failed++;
                break;
            case 'skipped':
                $this->skipped++;
                break;
            case 'broken':
                $this->broken++;
                break;
            case 'muted':
                $this->muted++;
                break;
        }
    }
}
