<?php

namespace Qase\PhpCommons\Models\Config;

class ApiConfig
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
}
