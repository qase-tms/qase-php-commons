<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Reporters\CoreReporter;
use Qase\PhpCommons\Reporters\FileReporter;
use Qase\PhpCommons\Reporters\ReporterFactory;
use Qase\PhpCommons\Reporters\TestOpsReporter;
use Qase\PhpCommons\Utils\StateManager;
use Qase\PhpCommons\Utils\StatusMapping;
use ReflectionClass;

/**
 * qase-tms/qase-php-commons#46: a run whose results could not be uploaded must not be
 * reused by the next invocation, and the report fallback must still write its report.
 */
class FailedRunStateTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/qase_failed_run_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
    }

    public function testNextInvocationCreatesANewRunAfterResultsFailedToUpload(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))->method('createTestRun')->willReturnOnConsecutiveCalls(2, 3);
        $client->method('sendResults')->willThrowException(new Exception('[422] params.param0 must be a string'));
        $client->expects($this->never())->method('completeTestRun');

        $this->invoke($client);
        $this->assertFileExists($this->dir . '/report/run.json', 'The report fallback must write its run');

        $secondRun = $this->createTestOpsReporter($client);
        $secondRun->startRun();

        $this->assertSame(3, $this->getPrivateProperty($secondRun, 'runId'));
    }

    public function testPrimaryAndFallbackReportersDoNotShareState(): void
    {
        $config = new QaseConfig();
        $config->setMode('testops');
        $config->setFallback('report');
        $config->testops->setProject('TEST');
        $config->testops->api->setToken('token');

        $primary = $this->createInternalReporter($config, false);
        $fallback = $this->createInternalReporter($config, true);

        $this->assertSame(StateManager::defaultFilename('testops'), $this->stateFilename($primary));
        $this->assertSame(StateManager::defaultFilename('report'), $this->stateFilename($fallback));
    }

    private function invoke(ClientInterface $client): void
    {
        $config = new QaseConfig();
        $config->report->connection->setPath($this->dir . '/report');

        $core = new CoreReporter(
            $this->createMock(LoggerInterface::class),
            $this->createTestOpsReporter($client),
            new FileReporter($this->createMock(LoggerInterface::class), $config->report, new StateManager($this->dir . '/report-state.json')),
            null,
            new StatusMapping($this->createMock(LoggerInterface::class))
        );

        $result = new Result();
        $result->title = 'falls back on a blank name';
        $result->execution->setStatus('passed');
        $result->execution->setThread('main');
        $result->execution->finish();

        $core->startRun();
        $core->addResult($result);
        $core->completeRun();
    }

    private function createTestOpsReporter(ClientInterface $client): TestOpsReporter
    {
        $config = new QaseConfig();
        $config->testops->setProject('TEST');
        $config->testops->api->setRetries(0);

        return new TestOpsReporter(
            $client,
            $config,
            new StateManager($this->dir . '/testops-state.json'),
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createInternalReporter(QaseConfig $config, bool $fallback): object
    {
        $method = (new ReflectionClass(ReporterFactory::class))->getMethod('createInternalReporter');
        $method->setAccessible(true);

        return $method->invoke(null, $this->createMock(LoggerInterface::class), $config, $fallback);
    }

    private function stateFilename(object $reporter): string
    {
        return $this->getPrivateProperty($this->getPrivateProperty($reporter, 'state'), 'filename');
    }

    private function removeDirectory(string $path): void
    {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($entryPath) ? $this->removeDirectory($entryPath) : unlink($entryPath);
        }
        rmdir($path);
    }

    private function getPrivateProperty(object $object, string $name)
    {
        $property = (new ReflectionClass($object))->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
