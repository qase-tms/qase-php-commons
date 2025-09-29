<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;

use Exception;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\ReporterInterface;
use Qase\PhpCommons\Utils\StatusMapping;

// Disable deprecated errors from Api clients
error_reporting(E_ALL & ~E_DEPRECATED);

class CoreReporter implements ReporterInterface
{
    /** @var InternalReporterInterface|null */
    private ?InternalReporterInterface $reporter;

    /** @var InternalReporterInterface|null */
    private ?InternalReporterInterface $fallbackReporter;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var string|null */
    private ?string $rootSuite;

    /** @var StatusMapping */
    private StatusMapping $statusMapping;

    public function __construct(LoggerInterface $logger, ?InternalReporterInterface $reporter, ?InternalReporterInterface $fallbackReporter, ?string $rootSuite, StatusMapping $statusMapping)
    {
        $this->logger = $logger;
        $this->reporter = $reporter;
        $this->fallbackReporter = $fallbackReporter;
        $this->rootSuite = $rootSuite;
        $this->statusMapping = $statusMapping;
    }

    public function startRun(): void
    {
        if ($this->reporter === null) {
            return;
        }

        $this->logger->info('Starting test run');

        try {
            $this->reporter->startRun();
        } catch (Exception $e) {
            $this->logger->error('Failed to start reporter: ' . $e->getMessage());
            $this->runFallbackReporter();
        }
    }

    public function completeRun(): void
    {
        if ($this->reporter === null) {
            return;
        }

        $this->logger->info('Completing test run');

        try {
            $this->reporter->completeRun();
        } catch (Exception $e) {
            $this->logger->error('Failed to complete reporter: ' . $e->getMessage());
            $this->runFallbackReporter();
        }
    }

    public function addResult($result): void
    {
        if ($this->reporter === null) {
            return;
        }

        $this->logger->debug("Adding result: " . json_encode($result));

        try {
            // Apply status mapping before processing
            $this->applyStatusMapping($result);

            if ($this->rootSuite !== null) {
                $suites = $result->relations->suite->data;
                $result->relations->suite->data = [];
                $result->relations->addSuite($this->rootSuite);
                foreach ($suites as $suite) {
                    $result->relations->addSuite($suite->title);
                }
            }

            $this->reporter->addResult($result);
        } catch (Exception $e) {
            $this->logger->error('Failed to add result to reporter: ' . $e->getMessage());
            $this->runFallbackReporter();
        }
    }

    private function runFallbackReporter(): void
    {
        if ($this->fallbackReporter === null) {
            return;
        }

        try {
            $results = $this->reporter->getResults();
            $this->fallbackReporter->startRun();
            $this->fallbackReporter->setResults($results);
            $this->reporter = $this->fallbackReporter;
            $this->fallbackReporter = null;
        } catch (Exception $e) {
            $this->logger->error('Failed to run fallback reporter: ' . $e->getMessage());
            $this->reporter = null;
        }
    }

    /**
     * Apply status mapping to result
     * 
     * @param mixed $result
     */
    private function applyStatusMapping($result): void
    {
        if ($this->statusMapping->isEmpty()) {
            return;
        }

        // Apply mapping to result execution status
        if (isset($result->execution) && isset($result->execution->status)) {
            $originalStatus = $result->execution->status;
            $mappedStatus = $this->statusMapping->mapStatus($originalStatus);
            
            if ($originalStatus !== $mappedStatus) {
                $result->execution->status = $mappedStatus;
                $this->logger->info("Status mapping applied to result '{$result->title}': '$originalStatus' -> '$mappedStatus'");
            }
        }

        // Apply mapping to step statuses
        if (isset($result->steps) && is_array($result->steps)) {
            foreach ($result->steps as $step) {
                if (isset($step->status)) {
                    $originalStatus = $step->status;
                    $mappedStatus = $this->statusMapping->mapStatus($originalStatus);
                    
                    if ($originalStatus !== $mappedStatus) {
                        $step->status = $mappedStatus;
                        $this->logger->info("Status mapping applied to step '{$step->title}': '$originalStatus' -> '$mappedStatus'");
                    }
                }
            }
        }
    }

    public function sendResults(): void
    {
        if ($this->reporter === null) {
            return;
        }

        try {
            $this->reporter->sendResults();
        } catch (Exception $e) {
            $this->logger->error('Failed to send results: ' . $e->getMessage());
            $this->runFallbackReporter();
        }
    }
}
