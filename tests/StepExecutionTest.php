<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Models\StepExecution;

class StepExecutionTest extends TestCase
{
    public function testFinishStampsEndTimeAndComputesDurationInMilliseconds(): void
    {
        $step = new StepExecution();
        $step->setStartTime(1000.0);

        $step->finish();

        $this->assertNotNull($step->getEndTime());
        $this->assertGreaterThanOrEqual($step->getStartTime(), $step->getEndTime());

        $expectedMs = (int) (($step->getEndTime() - $step->getStartTime()) * 1000);
        $this->assertSame($expectedMs, $step->getDuration());
    }

    public function testFinishPreservesSubSecondDurationsAsMilliseconds(): void
    {
        // Regression: previously duration was computed in seconds and cast to int,
        // which truncated any sub-second step to 0. Qase API v2 expects milliseconds.
        $step = new StepExecution();
        $now = microtime(true);
        $step->setStartTime($now - 0.25); // 250 ms ago

        $step->finish();

        $this->assertGreaterThanOrEqual(250, $step->getDuration());
        $this->assertLessThan(1000, $step->getDuration());
    }
}
