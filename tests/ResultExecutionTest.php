<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Models\ResultExecution;

class ResultExecutionTest extends TestCase
{
    public function testFinishStampsEndTimeAndComputesDurationInMilliseconds(): void
    {
        $execution = new ResultExecution();
        $execution->setStartTime(1000.0);

        $execution->finish();

        $this->assertNotNull($execution->getEndTime());
        $this->assertGreaterThanOrEqual($execution->getStartTime(), $execution->getEndTime());

        $expectedMs = (int) (($execution->getEndTime() - $execution->getStartTime()) * 1000);
        $this->assertSame($expectedMs, $execution->getDuration());
    }

    public function testFinishPreservesSubSecondDurationsAsMilliseconds(): void
    {
        // Regression: previously duration was computed in seconds and cast to int,
        // which truncated any sub-second run to 0. Qase API v2 expects milliseconds.
        $execution = new ResultExecution();
        $now = microtime(true);
        $execution->setStartTime($now - 0.25); // 250 ms ago

        $execution->finish();

        $this->assertGreaterThanOrEqual(250, $execution->getDuration());
        $this->assertLessThan(1000, $execution->getDuration());
    }

    public function testFinishConvertsMultiSecondRunsToMilliseconds(): void
    {
        $execution = new ResultExecution();
        $now = microtime(true);
        $execution->setStartTime($now - 2.0); // 2 s ago

        $execution->finish();

        $this->assertGreaterThanOrEqual(2000, $execution->getDuration());
        $this->assertLessThan(3000, $execution->getDuration());
    }
}
