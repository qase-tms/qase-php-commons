<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Config;

use Qase\PhpCommons\Interfaces\LoggerInterface;

abstract class BaseConfig
{
    public const REQUIRED_PARAMS = [];

    protected string $reporterName;

    public LoggerInterface $logger;

    public function __construct(string $reporterName, LoggerInterface $logger)
    {
        $this->reporterName = $reporterName;

        foreach ($_ENV as $envName => $envValue) {
            if (strpos($envName, "QASE_") === 0 && getenv($envName) === false) {
                putenv($envName . '=' . $envValue);
            }
        }
        $this->logger = $logger;
    }

    public function getReporterName(): string
    {
        return $this->reporterName;
    }

    protected function validate(): void
    {
        foreach (static::REQUIRED_PARAMS as $paramName) {
            if (!getenv($paramName)) {
                $invalidParams[] = $paramName;
            }
        }
        if (!empty($invalidParams)) {
            throw new \LogicException(sprintf(
                'The Qase %s reporter needs the following environment variable(s) to be set: %s.',
                $this->reporterName,
                implode(', ', $invalidParams)
            ));
        }
    }
}
