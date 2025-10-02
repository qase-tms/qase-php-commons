<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models\Config;


class QaseConfig
{
    public string $mode;
    public string $fallback;
    public ?string $environment = null;
    public ?string $rootSuite = null;
    public bool $debug;
    public array $statusMapping = [];
    public ?array $logging = null;
    public TestopsConfig $testops;
    public ReportConfig $report;

    public function __construct()
    {
        $this->mode = Mode::OFF;
        $this->fallback = Mode::OFF;
        $this->debug = false;
        $this->logging = null;
        $this->testops = new TestopsConfig();
        $this->report = new ReportConfig();
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): void
    {
        if (Mode::isValid($mode)) {
            $this->mode = $mode;
        }
    }

    public function setFallback(string $fallback): void
    {
        if (Mode::isValid($fallback)) {
            $this->fallback = $fallback;
        }
    }

    public function getFallback(): string
    {
        return $this->fallback;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): void
    {
        $this->environment = $environment;
    }

    public function getRootSuite(): ?string
    {
        return $this->rootSuite;
    }

    public function setRootSuite(?string $rootSuite): void
    {
        $this->rootSuite = $rootSuite;
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get status mapping configuration
     * 
     * @return array<string, string>
     */
    public function getStatusMapping(): array
    {
        return $this->statusMapping;
    }

    /**
     * Set status mapping configuration
     * 
     * @param array<string, string> $statusMapping
     */
    public function setStatusMapping(array $statusMapping): void
    {
        $this->statusMapping = $statusMapping;
    }

    /**
     * Get logging configuration
     * 
     * @return array|null
     */
    public function getLogging(): ?array
    {
        return $this->logging;
    }

    /**
     * Set logging configuration
     * 
     * @param array|null $logging
     */
    public function setLogging(?array $logging): void
    {
        $this->logging = $logging;
    }
}
