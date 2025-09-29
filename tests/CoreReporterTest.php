<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Reporters\CoreReporter;
use Qase\PhpCommons\Utils\StatusMapping;
use Exception;

class CoreReporterTest extends TestCase
{
    private $loggerMock;
    private $primaryReporterMock;
    private $fallbackReporterMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->primaryReporterMock = $this->createMock(InternalReporterInterface::class);
        $this->fallbackReporterMock = $this->createMock(InternalReporterInterface::class);
    }

    private function createCoreReporter(?InternalReporterInterface $primaryReporter = null, ?InternalReporterInterface $fallbackReporter = null, ?string $rootSuite = null): CoreReporter
    {
        $statusMapping = new StatusMapping($this->loggerMock);
        return new CoreReporter(
            $this->loggerMock,
            $primaryReporter ?? $this->primaryReporterMock,
            $fallbackReporter ?? $this->fallbackReporterMock,
            $rootSuite,
            $statusMapping
        );
    }

    public function testStartRunExecutesWithoutException(): void
    {
        $coreReporter = $this->createCoreReporter();

        $this->primaryReporterMock->expects($this->once())
            ->method('startRun');

        $coreReporter->startRun();
    }

    public function testStartRunFallbackOnException(): void
    {
        $coreReporter = $this->createCoreReporter();

        // Mock primary reporter to throw an exception
        $this->primaryReporterMock->expects($this->once())
            ->method('startRun')
            ->willThrowException(new Exception('Test exception'));

        // Expect the fallback reporter's startRun to be called
        $this->fallbackReporterMock->expects($this->once())
            ->method('startRun');

        // Start the run which will trigger the fallback
        $coreReporter->startRun();
    }

    public function testCompleteRunExecutesWithoutException(): void
    {
        $coreReporter = $this->createCoreReporter();

        $this->primaryReporterMock->expects($this->once())
            ->method('completeRun');

        $coreReporter->completeRun();
    }

    public function testCompleteRunFallbackOnException(): void
    {
        $coreReporter = $this->createCoreReporter();

        $this->primaryReporterMock->expects($this->once())
            ->method('completeRun')
            ->willThrowException(new Exception('Test exception'));

        $this->fallbackReporterMock->expects($this->once())
            ->method('startRun');

        $coreReporter->completeRun();
    }

    public function testAddResultExecutesWithoutException(): void
    {
        $result = new Result();

        $coreReporter = $this->createCoreReporter();

        $this->primaryReporterMock->expects($this->once())
            ->method('addResult')
            ->with($result);

        $coreReporter->addResult($result);
    }

    public function testAddResultFallbackOnException(): void
    {
        $coreReporter = $this->createCoreReporter();

        $this->primaryReporterMock->expects($this->once())
            ->method('addResult')
            ->willThrowException(new Exception('Test exception'));

        $this->fallbackReporterMock->expects($this->once())
            ->method('startRun');

        $coreReporter->addResult(new Result());
    }

    public function testRunFallbackReporterWhenPrimaryReporterFails(): void
    {
        $coreReporter = $this->createCoreReporter();

        // Mock failure of primary reporter
        $this->primaryReporterMock->expects($this->once())
            ->method('startRun')
            ->willThrowException(new Exception('Test exception'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Failed to start reporter: Test exception');

        // Run fallback reporter (triggered by getResults failure)
        $coreReporter->startRun();
    }

    public function testRunFallbackReporterWhenFallbackFails(): void
    {
        $coreReporter = $this->createCoreReporter();

        // Simulate primary reporter running, but fallback reporter fails
        $this->primaryReporterMock->expects($this->once())
            ->method('startRun')
            ->willThrowException(new Exception('Test exception'));;

        $this->fallbackReporterMock->expects($this->once())
            ->method('startRun')
            ->willThrowException(new Exception('Fallback start failed'));

        $this->loggerMock->expects($this->exactly(2))
            ->method('error')
            -> withAnyParameters();

        // Trigger fallback
        $coreReporter->startRun();
    }
}
