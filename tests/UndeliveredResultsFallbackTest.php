<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Reporters\CoreReporter;
use Qase\PhpCommons\Reporters\TestOpsReporter;
use Qase\PhpCommons\Utils\StatusMapping;

/**
 * End-to-end guarantee: results that the API never confirmed must reach the
 * fallback reporter instead of being destroyed by the failed request.
 */
class UndeliveredResultsFallbackTest extends TestCase
{
    public function testUndeliveredResultsAreHandedToTheFallbackReporter(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendResults')
            ->willThrowException(new Exception('Connection reset by peer'));

        $state = $this->createMock(StateInterface::class);
        $state->method('startRun')->willReturn(123);
        $state->expects($this->never())->method('completeRun');

        $config = new QaseConfig();
        $config->testops->setProject('TEST_PROJECT');
        $config->testops->run->setId(null);
        $config->testops->run->setComplete(true);
        $config->testops->batch->setSize(2);

        $testOpsReporter = new TestOpsReporter($client, $config, $state, $logger);

        $rescued = [];
        $fallback = $this->createMock(InternalReporterInterface::class);
        $fallback->method('setResults')
            ->willReturnCallback(function (array $results) use (&$rescued) {
                $rescued = $results;
            });
        $fallback->expects($this->once())->method('startRun');
        $fallback->expects($this->once())->method('completeRun');

        $core = new CoreReporter($logger, $testOpsReporter, $fallback, null, new StatusMapping($logger));
        $core->startRun();

        $core->addResult($this->makeResult('one'));
        $core->addResult($this->makeResult('two'));
        $core->addResult($this->makeResult('three'));

        $core->completeRun();

        $this->assertCount(3, $rescued, 'Every undelivered result must be handed to the fallback reporter');
        // completeRun() on the fallback is what actually persists them.
        $this->assertSame(['one', 'two', 'three'], array_map(fn($result) => $result->title, $rescued));
    }

    private function makeResult(string $title): Result
    {
        $result = new Result();
        $result->title = $title;
        $result->signature = $title;
        $result->execution->setStatus('passed');

        return $result;
    }
}
