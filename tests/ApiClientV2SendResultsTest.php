<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Client\ApiClientV2;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Config\TestopsConfig;
use Qase\PhpCommons\Models\Result;
use ReflectionClass;

class ApiClientV2SendResultsTest extends TestCase
{
    /** @var array<int, array> */
    private array $history = [];

    protected function setUp(): void
    {
        $this->history = [];
    }

    public function testSendResultsSucceedsWithoutRetryWhenTheServerAcceptsTheBatch(): void
    {
        $client = $this->createClient([$this->accepted()]);

        $client->sendResults('TEST', 1, [$this->makeResult()]);

        $this->assertCount(1, $this->history);
    }

    public function testSendResultsThrowsInsteadOfSwallowingAnUnrecoverableFailure(): void
    {
        $client = $this->createClient([
            new Response(400, [], '{"status":false}'),
        ]);

        $this->expectException(Exception::class);

        try {
            $client->sendResults('TEST', 1, [$this->makeResult()]);
        } finally {
            $this->assertCount(1, $this->history, 'A 400 must not be retried');
        }
    }

    public function testSendResultsRetriesServerErrorsUntilTheBatchIsAccepted(): void
    {
        $client = $this->createClient([
            new Response(500, [], 'boom'),
            new Response(503, [], 'boom'),
            $this->accepted(),
        ]);

        $client->sendResults('TEST', 1, [$this->makeResult()]);

        $this->assertCount(3, $this->history);
    }

    public function testSendResultsThrowsAfterRetriesAreExhausted(): void
    {
        $client = $this->createClient([
            new Response(500, [], 'boom'),
            new Response(500, [], 'boom'),
            new Response(500, [], 'boom'),
        ], 2);

        $this->expectException(Exception::class);

        try {
            $client->sendResults('TEST', 1, [$this->makeResult()]);
        } finally {
            $this->assertCount(3, $this->history, 'Two retries after the first attempt');
        }
    }

    public function testSendResultsRetriesRateLimiting(): void
    {
        $client = $this->createClient([
            new Response(429, ['Retry-After' => '0.001'], 'slow down'),
            $this->accepted(),
        ]);

        $client->sendResults('TEST', 1, [$this->makeResult()]);

        $this->assertCount(2, $this->history);
    }

    public function testEveryAttemptSendsTheSameIdempotencyKey(): void
    {
        $client = $this->createClient([
            new Response(500, [], 'boom'),
            $this->accepted(),
        ]);

        $result = $this->makeResult();
        $client->sendResults('TEST', 1, [$result]);

        $this->assertCount(2, $this->history);
        foreach ($this->history as $transaction) {
            $body = json_decode((string)$transaction['request']->getBody(), true);
            $this->assertSame($result->id, $body['results'][0]['id']);
        }
    }

    public function testRequestTimeoutIsAppliedToTheHttpClient(): void
    {
        $config = $this->createConfig();
        $config->api->setTimeout(17);

        $client = new ApiClientV2($this->createMock(LoggerInterface::class), $config, 'phpunit', 'qase-phpunit', ['system' => 'linux']);

        $guzzle = $this->getPrivateProperty($client, 'clientV2');
        $guzzleConfig = $this->getPrivateProperty($guzzle, 'config');

        $this->assertSame(17, $guzzleConfig['timeout']);
        $this->assertSame(17, $guzzleConfig['connect_timeout']);
    }

    private function accepted(): Response
    {
        return new Response(202, ['Content-Type' => 'application/json'], '{"status":true}');
    }

    private function createClient(array $responses, int $retries = 3): ApiClientV2
    {
        $config = $this->createConfig();
        $config->api->setRetries($retries);

        $client = new ApiClientV2($this->createMock(LoggerInterface::class), $config, 'phpunit', 'qase-phpunit', ['system' => 'linux']);

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $this->setPrivateProperty($client, 'clientV2', new Client(['handler' => $stack]));

        return $client;
    }

    private function createConfig(): TestopsConfig
    {
        $config = new TestopsConfig();
        $config->setProject('TEST');
        $config->api->setToken('token');
        $config->api->setRetryBackoff(0.001);

        return $config;
    }

    private function makeResult(): Result
    {
        $result = new Result();
        $result->title = 'test';
        $result->signature = 'test::signature';
        $result->execution->setStatus('passed');

        return $result;
    }

    private function getPrivateProperty(object $object, string $name)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    private function setPrivateProperty(object $object, string $name, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
