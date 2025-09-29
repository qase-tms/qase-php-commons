<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Loggers\Logger;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Models\ResultExecution;
use Qase\PhpCommons\Models\Step;
use Qase\PhpCommons\Models\StepExecution;
use Qase\PhpCommons\Reporters\CoreReporter;
use Qase\PhpCommons\Utils\StatusMapping;

class CoreReporterStatusMappingTest extends TestCase
{
    private Logger $logger;
    private StatusMapping $statusMapping;
    private MockInternalReporter $mockReporter;
    private CoreReporter $coreReporter;

    protected function setUp(): void
    {
        $this->logger = new Logger(false);
        $this->statusMapping = new StatusMapping($this->logger);
        $this->mockReporter = new MockInternalReporter();
        $this->coreReporter = new CoreReporter(
            $this->logger,
            $this->mockReporter,
            null,
            null,
            $this->statusMapping
        );
    }

    public function testStatusMappingAppliedToResultExecution(): void
    {
        // Set up mapping
        $this->statusMapping->setMapping(['invalid' => 'failed']);
        
        // Create test result
        $result = new Result();
        $result->title = 'Test Result';
        $result->execution = new ResultExecution('invalid');
        
        // Add result
        $this->coreReporter->addResult($result);
        
        // Check that status was mapped
        $this->assertEquals('failed', $result->execution->status);
    }

    public function testStatusMappingAppliedToSteps(): void
    {
        // Set up mapping
        $this->statusMapping->setMapping(['skipped' => 'passed']);
        
        // Create test result with steps
        $result = new Result();
        $result->title = 'Test Result';
        $result->execution = new ResultExecution('passed');
        
        // Add steps
        $step1 = new Step();
        $step1->title = 'Step 1';
        $step1->status = 'skipped';
        
        $step2 = new Step();
        $step2->title = 'Step 2';
        $step2->status = 'passed';
        
        $result->steps = [$step1, $step2];
        
        // Add result
        $this->coreReporter->addResult($result);
        
        // Check that step statuses were mapped
        $this->assertEquals('passed', $step1->status);
        $this->assertEquals('passed', $step2->status); // unchanged
    }

    public function testNoMappingWhenEmpty(): void
    {
        // No mapping set
        
        // Create test result
        $result = new Result();
        $result->title = 'Test Result';
        $result->execution = new ResultExecution('invalid');
        
        // Add result
        $this->coreReporter->addResult($result);
        
        // Check that status was not changed
        $this->assertEquals('invalid', $result->execution->status);
    }

    public function testMultipleMappings(): void
    {
        // Set up multiple mappings
        $this->statusMapping->setMapping([
            'invalid' => 'failed',
            'skipped' => 'passed'
        ]);
        
        // Create test result
        $result = new Result();
        $result->title = 'Test Result';
        $result->execution = new ResultExecution('invalid');
        
        // Add steps
        $step1 = new Step();
        $step1->title = 'Step 1';
        $step1->status = 'skipped';
        
        $step2 = new Step();
        $step2->title = 'Step 2';
        $step2->status = 'blocked';
        
        $result->steps = [$step1, $step2];
        
        // Add result
        $this->coreReporter->addResult($result);
        
        // Check mappings
        $this->assertEquals('failed', $result->execution->status);
        $this->assertEquals('passed', $step1->status);
        $this->assertEquals('blocked', $step2->status); // no mapping
    }

    public function testResultAddedToReporter(): void
    {
        $result = new Result();
        $result->title = 'Test Result';
        $result->execution = new ResultExecution('passed');
        
        $this->coreReporter->addResult($result);
        
        // Check that result was added to mock reporter
        $this->assertCount(1, $this->mockReporter->getResults());
        $this->assertEquals($result, $this->mockReporter->getResults()[0]);
    }
}

/**
 * Mock internal reporter for testing
 */
class MockInternalReporter implements InternalReporterInterface
{
    private array $results = [];

    public function startRun(): void
    {
        // Mock implementation
    }

    public function completeRun(): void
    {
        // Mock implementation
    }

    public function addResult($result): void
    {
        $this->results[] = $result;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    public function sendResults(): void
    {
        // Mock implementation
    }
}
