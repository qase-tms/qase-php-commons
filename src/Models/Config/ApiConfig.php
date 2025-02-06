<?php

namespace Qase\PhpCommons\Models\Config;

use JsonSerializable;

class ApiConfig implements JsonSerializable
{
    public ?string $token = null;
    public string $host;

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

    public function jsonSerialize(): array
    {
        return [
            'token' => $this->token ? $this->maskString($this->token) : null,
            'host' => $this->host
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
