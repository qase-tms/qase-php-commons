<?php

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;
use Qase\PhpCommons\Reporters\TestOpsReporter;
use ReflectionClass;

class TestOpsReporterTest extends TestCase
{
    private $clientMock;
    private $stateMock;
    private QaseConfig $config;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->stateMock = $this->createMock(StateInterface::class);
        $this->config = $this->getConfig();
    }

    public function testStartRunCreatesNewRunIfRunIdIsNull(): void
    {
        $this->config->testops->run->setId(null);

        $this->stateMock->method('startRun')
            ->willReturn(123);

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        $this->assertSame(123, $this->getPrivateProperty($reporter, 'runId'));
    }

    public function testStartRunThrowsExceptionIfRunNotFound(): void
    {
        $this->config->testops->run->setId(123);

        $this->clientMock->method('isTestRunExist')
            ->with('TEST_PROJECT', 123)
            ->willReturn(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Run with id 123 not found');

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();
    }

    public function testStartRunUsesExistingRunId(): void
    {
        $this->config->testops->run->setId(123);

        $this->clientMock->method('isTestRunExist')
            ->with('TEST_PROJECT', 123)
            ->willReturn(true);

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        $this->assertSame(123, $this->getPrivateProperty($reporter, 'runId'));
    }

    public function testSendResultsClearsResults(): void
    {
        $this->stateMock->method('startRun')->willReturn(123);

        $this->clientMock->expects($this->once())
            ->method('sendResults')
            ->with('TEST_PROJECT', 123, ['result1', 'result2']);

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        $reporter->addResult('result1');
        $reporter->addResult('result2');
        $reporter->sendResults();

        $this->assertEmpty($this->getPrivateProperty($reporter, 'results'));
    }

    public function testAddResultSendsResultsWhenBatchSizeIsReached(): void
    {
        $this->stateMock->method('startRun')->willReturn(123);

        $this->clientMock->expects($this->once())
            ->method('sendResults')
            ->with('TEST_PROJECT', 123, ['result1', 'result2']);

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        $reporter->addResult('result1');
        $reporter->addResult('result2');
    }

    public function testCompleteRunSendsRemainingResults(): void
    {
        $this->stateMock->method('startRun')->willReturn(123);

        $invocations = [];

        $this->clientMock
            ->method('sendResults')
            ->willReturnCallback(function ($project, $runId, $results) use (&$invocations) {
                $invocations[] = $results;
            });

        $this->stateMock->expects($this->once())
            ->method('completeRun')
            ->with($this->callback(function ($callback) {
                return is_callable($callback);
            }));

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        $reporter->addResult('result1');
        $reporter->addResult('result2');
        $reporter->addResult('result3');
        $reporter->completeRun();

        // Проверяем, что вызовы были выполнены в нужном порядке
        $this->assertCount(2, $invocations);
        $this->assertSame(['result1', 'result2'], $invocations[0]);
        $this->assertSame(['result3'], $invocations[1]);
    }

    public function testCompleteRunWithNoResultsDoesNothing(): void
    {
        $this->stateMock->method('startRun')->willReturn(123);

        $this->clientMock->expects($this->never())
            ->method('sendResults');

        $this->stateMock->expects($this->once())
            ->method('completeRun')
            ->with($this->isType('callable'));

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();
        $reporter->completeRun();
    }

    public function testCompleteRunWithCompleteIsFalseDoesNotCompleteTestRun(): void
    {
        $this->config->testops->run->setComplete(false);

        $this->stateMock->method('startRun')->willReturn(123);

        $this->clientMock->expects($this->never())
            ->method('completeTestRun');

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();
        $reporter->completeRun();
    }

    public function testAddResultFiltersOutExcludedStatuses(): void
    {
        $this->stateMock->method('startRun')->willReturn(123);
        
        // Configure status filter to exclude 'skipped' and 'blocked'
        $this->config->testops->setStatusFilter(['skipped', 'blocked']);

        $this->clientMock->expects($this->once())
            ->method('sendResults')
            ->with('TEST_PROJECT', 123, [
                $this->createResultWithStatus('passed'),
                $this->createResultWithStatus('failed')
            ]);

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        // Add results with different statuses
        $reporter->addResult($this->createResultWithStatus('passed'));
        $reporter->addResult($this->createResultWithStatus('skipped')); // Should be filtered out
        $reporter->addResult($this->createResultWithStatus('blocked')); // Should be filtered out
        $reporter->addResult($this->createResultWithStatus('failed'));

        $reporter->sendResults();
    }

    public function testAddResultIncludesAllResultsWhenNoFilterConfigured(): void
    {
        $this->stateMock->method('startRun')->willReturn(123);
        
        // No status filter configured
        $this->config->testops->setStatusFilter([]);

        $this->clientMock->expects($this->exactly(2))
            ->method('sendResults')
            ->withConsecutive(
                ['TEST_PROJECT', 123, [
                    $this->createResultWithStatus('passed'),
                    $this->createResultWithStatus('skipped')
                ]],
                ['TEST_PROJECT', 123, [
                    $this->createResultWithStatus('blocked'),
                    $this->createResultWithStatus('failed')
                ]]
            );

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        // Add results with different statuses - all should be included
        $reporter->addResult($this->createResultWithStatus('passed'));
        $reporter->addResult($this->createResultWithStatus('skipped'));
        $reporter->addResult($this->createResultWithStatus('blocked'));
        $reporter->addResult($this->createResultWithStatus('failed'));
    }

    public function testAddResultHandlesResultsWithoutStatus(): void
    {
        $this->stateMock->method('startRun')->willReturn(123);
        
        // Configure status filter
        $this->config->testops->setStatusFilter(['skipped']);

        $this->clientMock->expects($this->once())
            ->method('sendResults')
            ->with('TEST_PROJECT', 123, [
                $this->createResultWithoutStatus(),
                $this->createResultWithStatus('passed')
            ]);

        $reporter = new TestOpsReporter($this->clientMock, $this->config, $this->stateMock);
        $reporter->startRun();

        // Add results - one without status should be included
        $reporter->addResult($this->createResultWithoutStatus());
        $reporter->addResult($this->createResultWithStatus('skipped')); // Should be filtered out
        $reporter->addResult($this->createResultWithStatus('passed'));

        $reporter->sendResults();
    }

    private function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    private function getConfig(): QaseConfig
    {
        $config = new QaseConfig();

        $config->setEnvironment('environment');

        $config->testops->setProject('TEST_PROJECT');
        $config->testops->run->setId(null);
        $config->testops->run->setComplete(true);
        $config->testops->batch->setSize(2);

        return $config;
    }

    private function createResultWithStatus(string $status): object
    {
        $result = new \stdClass();
        $result->execution = new \stdClass();
        $result->execution->status = $status;
        return $result;
    }

    private function createResultWithoutStatus(): object
    {
        $result = new \stdClass();
        $result->execution = new \stdClass();
        // No status set
        return $result;
    }
}
