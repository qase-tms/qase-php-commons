<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;

use Exception;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;

class TestOpsReporter implements InternalReporterInterface
{
    private array $results = [];
    private ClientInterface $client;
    private QaseConfig $config;
    private StateInterface $state;
    private ?int $runId = null;

    public function __construct(ClientInterface $client, QaseConfig $config, StateInterface $state)
    {
        $this->client = $client;
        $this->config = $config;
        $this->state = $state;
    }

    /**
     * @throws Exception
     */
    public function startRun(): void
    {
        $this->runId = $this->getRunId();

        if ($this->runId !== null && !$this->client->isTestRunExist($this->config->testops->getProject(), $this->runId)) {
            throw new Exception("Run with id {$this->runId} not found");
        }

        if ($this->runId === null) {
            $this->runId = $this->state->startRun(function () {
                return $this->createNewRun();
            });
        }
    }

    public function completeRun(): void
    {
        $this->sendResults();

        if (!$this->config->testops->run->isComplete()) {
            return;
        }

        $this->state->completeRun(
            function () {
                $this->client->completeTestRun($this->config->testops->getProject(), $this->runId);
            });
    }

    public function addResult($result): void
    {
        $this->results[] = $result;

        if (count($this->results) >= $this->config->testops->batch->getSize()) {
            $this->flushResults();
        }
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    private function getRunId(): ?int
    {
        return $this->config->testops->run->getId();
    }

    private function createNewRun(): int
    {
        $envId = $this->getEnvironmentId($this->config->getEnvironment());

        return $this->client->createTestRun(
            $this->config->testops->getProject(),
            $this->config->testops->run->getTitle(),
            $this->config->testops->run->getDescription(),
            $this->config->testops->plan->getId(),
            $envId
        );
    }

    private function getEnvironmentId(?string $name): ?int
    {
        return $name ? $this->client->getEnvironment($this->config->testops->getProject(), $name) : null;
    }

    public function sendResults(): void
    {
        while (!empty($this->results)) {
            $this->flushResults();
        }
    }

    private function flushResults(): void
    {
        $chunk = array_splice($this->results, 0, $this->config->testops->batch->getSize());
        $this->sendResultsByBatch($chunk);
    }

    private function sendResultsByBatch(array $results): void
    {
        $this->client->sendResults($this->config->testops->getProject(), $this->runId, $results);
    }
}
