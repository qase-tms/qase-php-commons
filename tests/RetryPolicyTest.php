<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Qase\APIClientV2\ApiException;
use Qase\PhpCommons\Client\RetryPolicy;
use Qase\PhpCommons\Interfaces\LoggerInterface;

class RetryPolicyTest extends TestCase
{
    private array $sleeps = [];

    protected function setUp(): void
    {
        $this->sleeps = [];
    }

    public function testReturnsCallbackResultWithoutRetryingOnSuccess(): void
    {
        $attempts = 0;
        $policy = $this->createPolicy(3, 1.0);

        $result = $policy->execute('send results', function () use (&$attempts) {
            $attempts++;
            return 'sent';
        });

        $this->assertSame('sent', $result);
        $this->assertSame(1, $attempts);
        $this->assertSame([], $this->sleeps);
    }

    public function testRetriesTransportFailureUntilItSucceeds(): void
    {
        $attempts = 0;
        $policy = $this->createPolicy(3, 1.0);

        $result = $policy->execute('send results', function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('Connection reset by peer');
            }
            return 'sent';
        });

        $this->assertSame('sent', $result);
        $this->assertSame(3, $attempts);
        $this->assertCount(2, $this->sleeps);
    }

    /**
     * @dataProvider retryableStatusProvider
     */
    public function testRetriesRetryableHttpStatuses(int $status): void
    {
        $attempts = 0;
        $policy = $this->createPolicy(2, 1.0);

        try {
            $policy->execute('send results', function () use (&$attempts, $status) {
                $attempts++;
                throw new ApiException('failed', $status);
            });
            $this->fail('Expected the exception to be rethrown after retries are exhausted');
        } catch (ApiException $e) {
            $this->assertSame($status, $e->getCode());
        }

        $this->assertSame(3, $attempts, "Status {$status} must be retried");
        $this->assertCount(2, $this->sleeps);
    }

    public function retryableStatusProvider(): array
    {
        return [
            'request timeout' => [408],
            'too many requests' => [429],
            'internal server error' => [500],
            'bad gateway' => [502],
            'service unavailable' => [503],
            'gateway timeout' => [504],
        ];
    }

    /**
     * @dataProvider nonRetryableStatusProvider
     */
    public function testDoesNotRetryNonRetryableHttpStatuses(int $status): void
    {
        $attempts = 0;
        $policy = $this->createPolicy(3, 1.0);

        try {
            $policy->execute('send results', function () use (&$attempts, $status) {
                $attempts++;
                throw new ApiException('failed', $status);
            });
            $this->fail('Expected the exception to be rethrown immediately');
        } catch (ApiException $e) {
            $this->assertSame($status, $e->getCode());
        }

        $this->assertSame(1, $attempts, "Status {$status} must not be retried");
        $this->assertSame([], $this->sleeps);
    }

    public function nonRetryableStatusProvider(): array
    {
        return [
            'bad request' => [400],
            'unauthorized' => [401],
            'forbidden' => [403],
            'not found' => [404],
            'payload too large' => [413],
            'unprocessable entity' => [422],
            'insufficient storage' => [507],
        ];
    }

    public function testUsesExponentialBackoffBetweenAttempts(): void
    {
        $policy = $this->createPolicy(3, 0.5);

        try {
            $policy->execute('send results', function () {
                throw new ApiException('failed', 500);
            });
        } catch (ApiException $e) {
            // expected
        }

        $this->assertSame([0.5, 1.0, 2.0], $this->sleeps);
    }

    public function testRetryAfterHeaderOverridesComputedBackoff(): void
    {
        $policy = $this->createPolicy(1, 1.0);

        try {
            $policy->execute('send results', function () {
                throw new ApiException('rate limited', 429, ['Retry-After' => ['60']]);
            });
        } catch (ApiException $e) {
            // expected
        }

        $this->assertSame([60.0], $this->sleeps);
    }

    public function testRetryAfterHeaderIsMatchedCaseInsensitively(): void
    {
        $policy = $this->createPolicy(1, 1.0);

        try {
            $policy->execute('send results', function () {
                throw new ApiException('rate limited', 429, ['retry-after' => ['45']]);
            });
        } catch (ApiException $e) {
            // expected
        }

        $this->assertSame([45.0], $this->sleeps);
    }

    public function testRetryAfterHttpDateIsConvertedToSeconds(): void
    {
        $policy = $this->createPolicy(1, 1.0);
        $date = gmdate('D, d M Y H:i:s', time() + 30) . ' GMT';

        try {
            $policy->execute('send results', function () use ($date) {
                throw new ApiException('rate limited', 429, ['Retry-After' => [$date]]);
            });
        } catch (ApiException $e) {
            // expected
        }

        $this->assertCount(1, $this->sleeps);
        $this->assertGreaterThan(25.0, $this->sleeps[0]);
        $this->assertLessThanOrEqual(31.0, $this->sleeps[0]);
    }

    public function testRetryAfterIsCappedToProtectAgainstAbsurdValues(): void
    {
        $policy = $this->createPolicy(1, 1.0);

        try {
            $policy->execute('send results', function () {
                throw new ApiException('rate limited', 429, ['Retry-After' => ['99999']]);
            });
        } catch (ApiException $e) {
            // expected
        }

        $this->assertSame([300.0], $this->sleeps);
    }

    public function testZeroRetriesMeansSingleAttempt(): void
    {
        $attempts = 0;
        $policy = $this->createPolicy(0, 1.0);

        try {
            $policy->execute('send results', function () use (&$attempts) {
                $attempts++;
                throw new ApiException('failed', 503);
            });
        } catch (ApiException $e) {
            // expected
        }

        $this->assertSame(1, $attempts);
        $this->assertSame([], $this->sleeps);
    }

    public function testRethrowsLastFailureAfterRetriesAreExhausted(): void
    {
        $policy = $this->createPolicy(2, 1.0);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('third failure');

        $attempts = 0;
        $policy->execute('send results', function () use (&$attempts) {
            $attempts++;
            throw new ApiException('third failure', 500);
        });
    }

    private function createPolicy(int $retries, float $backoff): RetryPolicy
    {
        $logger = $this->createMock(LoggerInterface::class);

        return new RetryPolicy(
            $logger,
            $retries,
            $backoff,
            function (float $seconds): void {
                $this->sleeps[] = $seconds;
            }
        );
    }
}
