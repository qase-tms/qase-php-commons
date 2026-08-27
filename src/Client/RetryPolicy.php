<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Client;

use Qase\PhpCommons\Interfaces\LoggerInterface;
use Throwable;

/**
 * Retries an API call that failed for a transient reason.
 *
 * Retrying is only safe because every result carries an idempotency key
 * (see ApiClientV2::convertToModel(), $model->setId()), so a batch that was
 * accepted but whose response was lost is not duplicated on the next attempt.
 */
class RetryPolicy
{
    /**
     * Statuses that fail exactly the same way on a second attempt.
     */
    private const NON_RETRYABLE_STATUSES = [400, 401, 403, 404, 413, 422, 507];

    /**
     * Statuses that are worth another attempt (in addition to 5xx).
     */
    private const RETRYABLE_STATUSES = [408, 429];

    /**
     * Upper bound for a server-provided Retry-After, in seconds.
     */
    private const MAX_RETRY_AFTER = 300.0;

    private LoggerInterface $logger;
    private int $retries;
    private float $backoff;

    /** @var callable(float): void */
    private $sleeper;

    /**
     * @param int $retries Number of additional attempts after the first one
     * @param float $backoff Base delay in seconds, doubled on every attempt
     * @param callable(float): void|null $sleeper Delay function, overridable in tests
     */
    public function __construct(LoggerInterface $logger, int $retries = 3, float $backoff = 1.0, ?callable $sleeper = null)
    {
        $this->logger = $logger;
        $this->retries = max(0, $retries);
        $this->backoff = $backoff > 0 ? $backoff : 1.0;
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            usleep((int)round($seconds * 1_000_000));
        };
    }

    /**
     * Execute the callback, retrying transient failures.
     *
     * @param string $action Human-readable description for log messages
     * @param callable $callback
     * @return mixed The callback result
     * @throws Throwable The last failure, once retries are exhausted or the failure is permanent
     */
    public function execute(string $action, callable $callback)
    {
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (Throwable $e) {
                if ($attempt >= $this->retries || !$this->isRetryable($e)) {
                    throw $e;
                }

                $delay = $this->resolveDelay($e, $attempt);
                $this->logger->warning(sprintf(
                    'Failed to %s (attempt %d of %d): %s. Retrying in %.1fs',
                    $action,
                    $attempt + 1,
                    $this->retries + 1,
                    $e->getMessage(),
                    $delay
                ));

                ($this->sleeper)($delay);
                $attempt++;
            }
        }
    }

    private function isRetryable(Throwable $e): bool
    {
        $status = (int)$e->getCode();

        // Not an HTTP status: a transport failure (connection reset, DNS, timeout).
        if ($status < 100 || $status > 599) {
            return true;
        }

        if (in_array($status, self::NON_RETRYABLE_STATUSES, true)) {
            return false;
        }

        return in_array($status, self::RETRYABLE_STATUSES, true) || $status >= 500;
    }

    private function resolveDelay(Throwable $e, int $attempt): float
    {
        $retryAfter = $this->getRetryAfter($e);
        if ($retryAfter !== null) {
            return $retryAfter;
        }

        return $this->backoff * (2 ** $attempt);
    }

    /**
     * Read the Retry-After header from an API exception, if it carries one.
     */
    private function getRetryAfter(Throwable $e): ?float
    {
        if (!method_exists($e, 'getResponseHeaders')) {
            return null;
        }

        $headers = $e->getResponseHeaders();
        if (!is_array($headers)) {
            return null;
        }

        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) !== 'retry-after') {
                continue;
            }

            $raw = is_array($value) ? ($value[0] ?? null) : $value;
            if (!is_string($raw) && !is_numeric($raw)) {
                return null;
            }

            return $this->parseRetryAfter(trim((string)$raw));
        }

        return null;
    }

    private function parseRetryAfter(string $raw): ?float
    {
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $seconds = (float)$raw;
        } else {
            $timestamp = strtotime($raw);
            if ($timestamp === false) {
                return null;
            }
            $seconds = (float)($timestamp - time());
        }

        if ($seconds <= 0) {
            return null;
        }

        return min($seconds, self::MAX_RETRY_AFTER);
    }
}
