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
    private ?array $cachedConfigurationGroups = null;

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
            }
        );
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
        $configurations = $this->prepareConfigurations();

        return $this->client->createTestRun(
            $this->config->testops->getProject(),
            $this->config->testops->run->getTitle(),
            $this->config->testops->run->getDescription(),
            $this->config->testops->plan->getId(),
            $envId,
            $this->config->testops->run->getTags(),
            $configurations
        );
    }

    private function prepareConfigurations(): ?array
    {
        $configValues = $this->config->testops->configurations->getValues();
        if (empty($configValues)) {
            return null;
        }

        $configurations = [];
        foreach ($configValues as $configItem) {
            if (!isset($configItem['name']) || !isset($configItem['value'])) {
                continue;
            }
            
            // Try to find existing configuration group or create new one
            $groupId = $this->findOrCreateConfigurationGroup($configItem['name']);
            if ($groupId) {
                $itemId = $this->findOrCreateConfigurationItem($groupId, $configItem['value']);
                if ($itemId) {
                    $configurations[] = $itemId;
                }
            }
        }

        return empty($configurations) ? null : $configurations;
    }

    private function findOrCreateConfigurationGroup(string $title): ?int
    {
        // Get cached groups or fetch from API
        if ($this->cachedConfigurationGroups === null) {
            $this->cachedConfigurationGroups = $this->client->getConfigurationGroups($this->config->testops->getProject());
        }
        
        // Try to find existing group
        foreach ($this->cachedConfigurationGroups as $group) {
            if ($group->getTitle() === $title) {
                return $group->getId();
            }
        }

        // Create new group if createIfNotExists is enabled
        if ($this->config->testops->configurations->isCreateIfNotExists()) {
            $newGroup = $this->client->createConfigurationGroup(
                $this->config->testops->getProject(),
                $title
            );
            return $newGroup ? $newGroup->getId() : null;
        }

        // If group not found and createIfNotExists is false, return null
        return null;
    }

    private function findOrCreateConfigurationItem(int $groupId, string $title): ?int
    {
        // First try to find existing item in the group
        $existingItemId = $this->findExistingConfigurationItem($groupId, $title);
        if ($existingItemId) {
            return $existingItemId;
        }

        // Create new item only if createIfNotExists is enabled
        if ($this->config->testops->configurations->isCreateIfNotExists()) {
            $newItem = $this->client->createConfigurationItem(
                $this->config->testops->getProject(),
                $groupId,
                $title
            );
            return $newItem ? $newItem->getId() : null;
        }

        return null;
    }

    private function findExistingConfigurationItem(int $groupId, string $title): ?int
    {
        // Use cached groups
        if ($this->cachedConfigurationGroups === null) {
            $this->cachedConfigurationGroups = $this->client->getConfigurationGroups($this->config->testops->getProject());
        }
        
        // Find the specific group
        foreach ($this->cachedConfigurationGroups as $group) {
            if ($group->getId() === $groupId) {
                // Look for existing item with the same title
                foreach ($group->items as $item) {
                    if ($item->getTitle() === $title) {
                        return $item->getId();
                    }
                }
                break;
            }
        }
        
        return null;
    }

    private function resetConfigurationCache(): void
    {
        $this->cachedConfigurationGroups = null;
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
