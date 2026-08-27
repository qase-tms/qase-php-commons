<?php

namespace Qase\PhpCommons\Models\Config;

use JsonSerializable;

class ApiConfig implements JsonSerializable
{
    private const DEFAULT_TIMEOUT = 30;

    public ?string $token = null;
    public string $host;

    /**
     * HTTP request timeout in seconds.
     */
    public int $timeout = self::DEFAULT_TIMEOUT;

    /**
     * Number of additional attempts after a transient API failure.
     */
    public int $retries = 3;

    /**
     * Base delay in seconds between retries, doubled on every attempt.
     */
    public float $retryBackoff = 1.0;

    public function __construct()
    {
        $this->host = 'qase.io';
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * A non-positive timeout is ignored: it would let a stalled connection
     * hang the process at teardown instead of failing.
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function setRetries(int $retries): void
    {
        $this->retries = $retries;
    }

    public function getRetryBackoff(): float
    {
        return $this->retryBackoff;
    }

    public function setRetryBackoff(float $retryBackoff): void
    {
        $this->retryBackoff = $retryBackoff;
    }

    public function jsonSerialize(): array
    {
        return [
            'token' => $this->token ? $this->maskString($this->token) : null,
            'host' => $this->host,
            'timeout' => $this->timeout,
            'retries' => $this->retries,
            'retryBackoff' => $this->retryBackoff
        ];
    }

    private function maskString(string $str): string
    {
        $len = strlen($str);
        if ($len <= 7) {
            return str_repeat('*', $len);
        }

        return substr($str, 0, 3) . str_repeat('*', $len - 7) . substr($str, -4);
    }
}
