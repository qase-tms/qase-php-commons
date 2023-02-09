<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Qase\Client\Api\ResultsApi;
use Qase\Client\Api\RunsApi;
use Qase\Client\ApiException;
use Qase\Client\Model\IdResponse;
use Qase\Client\Model\IdResponseAllOfResult;
use Qase\PhpClientUtils\Config;
use Qase\PhpClientUtils\ConsoleLogger;
use Qase\PhpClientUtils\Repository;
use Qase\PhpClientUtils\ResultHandler;
use Qase\PhpClientUtils\ResultsConverter;
use Qase\PhpClientUtils\RunResult;

class ResultHandlerTest extends TestCase
{
    /**
     * @dataProvider runIdDataProvider
     */
    public function testSuccessfulHandling(?int $runId, string $testName): void
    {
        $runResult = new RunResult($this->createConfig('PRJ', $runId));
        $runResult->addResult([
            'status' => 'passed',
            'time' => 123,
            'stacktrace' => '',
            'full_test_name' => SomeTest::class . '::' . $testName,
        ]);

        $response = $this->runResultsHandler($runResult);

        $this->assertTrue($response->getStatus());
    }

    public function runIdDataProvider(): array
    {
        return [
            [1, 'testImportantStuff'],
            [10, 'testAwesomeStuff'],
            [null, 'testImportantStuff']
        ];
    }

    public function testHandlingWithNoResults(): void
    {
        $runResult = new RunResult($this->createConfig());

        $response = $this->runResultsHandler($runResult);

        $this->assertNull($response);
    }

    public function testRunName()
    {
        // Arrange
        $testingRunName = 'testing Run Name';
        $config = $this->createConfig();
        $config->method('getRunName')->willReturn($testingRunName);
        $runResult = $this->createRunResultWithData($config);
        $repository = $this->createRepository();

        // Assert using callback
        $repository->getRunsApi()->expects($this->once())
            ->method('createRun')
            ->with(
                $this->anything(),
                $this->callback(function ($runBody) use ($testingRunName) {
                    return $testingRunName === $runBody->getTitle();
                })
            );

        // Act
        $this->runResultsHandler($runResult, $repository);
    }

    public function testDefaultRunName()
    {
        // Arrange
        $config = $this->createConfig();
        $config->method('getRunName')->willReturn(null);
        $runResult = $this->createRunResultWithData($config);
        $repository = $this->createRepository();

        // Assert using callback
        $repository->getRunsApi()->expects($this->once())
            ->method('createRun')
            ->with(
                $this->anything(),
                $this->callback(function ($runBody) {
                    return strpos($runBody->getTitle(), 'Automated run') === 0;
                })
            );

        // Act
        $this->runResultsHandler($runResult, $repository);
    }

    /**
     * @throws ApiException
     */
    public function testRunDescription(): void
    {
        // Arrange
        $testingDescription = 'testing Description';
        $config = $this->createConfig();
        $config->method('getRunDescription')->willReturn($testingDescription);
        $runResult = $this->createRunResultWithData($config);
        $repository = $this->createRepository();

        // Assert using callback
        $repository->getRunsApi()->expects($this->once())
            ->method('createRun')
            ->with(
                $this->anything(),
                $this->callback(function ($runBody) use ($testingDescription) {
                    return $testingDescription === $runBody->getDescription();
                })
            );

        // Act
        $this->runResultsHandler($runResult, $repository);
    }

    private function createRepository(): Repository
    {
        $runsApi = $this->getMockBuilder(RunsApi::class)->getMock();
        $runsApi->method('createRun')->willReturn(
            new IdResponse([
                'status' => true,
                'result' => new IdResponseAllOfResult(['id' => 88,]),
            ])
        );

        $client = $this->getMockBuilder(Client::class)->getMock();
        $client->method('send')->willReturn(
            new Response(200, [], json_encode(['status' => true]))
        );

        $repository = $this->getMockBuilder(Repository::class)->getMock();
        $repository->method('getResultsApi')->willReturn(
            new ResultsApi($client)
        );
        $repository->method('getRunsApi')->willReturn(
            $runsApi
        );

        return $repository;
    }

    private function createLogger(): ConsoleLogger
    {
        return $this->getMockBuilder(ConsoleLogger::class)->getMock();
    }

    private function createConverter(): ResultsConverter
    {
        return new ResultsConverter($this->createLogger());
    }

    private function createConfig(string $projectCode = 'PRJ', ?int $runId = null): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('getRunId')->willReturn($runId);
        $config->method('getProjectCode')->willReturn($projectCode);
        $config->method('getEnvironmentId')->willReturn(null);

        return $config;
    }

    private function runResultsHandler(RunResult $runResult, ?Repository $repository = null): ?\Qase\Client\Model\Response
    {
        $handler = new ResultHandler(
            $repository ?: $this->createRepository(),
            $this->createConverter(),
            $this->createLogger()
        );

        return $handler->handle($runResult, '');
    }

    private function createRunResultWithData(Config $config): RunResult
    {
        $runResult = new RunResult($config);
        $runResult->addResult([
            'status' => 'passed',
            'time' => 123,
            'stacktrace' => '',
            'full_test_name' => 'Tests\SomeTest::testImportantStuff',
        ]);

        return $runResult;
    }
}
