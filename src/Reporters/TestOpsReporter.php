<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;

use Exception;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;

class TestOpsReporter implements InternalReporterInterface
{
    private array $results = [];
    private ClientInterface $client;
    private QaseConfig $config;
    private StateInterface $state;
    private LoggerInterface $logger;
    private ?int $runId = null;
    private ?array $cachedConfigurationGroups = null;

    public function __construct(ClientInterface $client, QaseConfig $config, StateInterface $state, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->config = $config;
        $this->state = $state;
        $this->logger = $logger;
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
        // Apply status filter if configured
        if (!$this->shouldIncludeResult($result)) {
            return;
        }

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

        $runId = $this->client->createTestRun(
            $this->config->testops->getProject(),
            $this->config->testops->run->getTitle(),
            $this->config->testops->run->getDescription(),
            $this->config->testops->plan->getId(),
            $envId,
            $this->config->testops->run->getTags(),
            $configurations
        );

        // Update external issue if configured
        if ($this->config->testops->run->getExternalLink()) {
            $this->updateExternalIssue($runId);
        }

        return $runId;
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

    /**
     * Check if result should be included based on status filter
     * 
     * @param mixed $result The result object to check
     * @return bool True if result should be included, false otherwise
     */
    private function shouldIncludeResult($result): bool
    {
        $statusFilter = $this->config->testops->getStatusFilter();
        
        // If no filter is configured, include all results
        if (empty($statusFilter)) {
            return true;
        }

        // Get result status
        $status = null;
        if (isset($result->execution) && isset($result->execution->status)) {
            $status = $result->execution->status;
        } elseif (isset($result->status)) {
            $status = $result->status;
        }

        // If status is not found, include the result
        if ($status === null) {
            return true;
        }

        // Exclude result if its status is in the filter list
        return !in_array($status, $statusFilter, true);
    }

    /**
     * Update external issue for the test run
     * 
     * @param int $runId Test run ID
     */
    private function updateExternalIssue(int $runId): void
    {
        try {
            $externalLink = $this->config->testops->run->getExternalLink();
            if (!$externalLink) {
                return;
            }

            // Update external issue for the test run
            $this->client->runUpdateExternalIssue(
                $this->config->testops->getProject(),
                $externalLink->getType(),
                [
                    [
                        'run_id' => $runId,
                        'external_issue' => $externalLink->getLink(),
                    ]
                ]
            );
        } catch (Exception $e) {
            // Log error through the centralized logger
            $this->logger->error('Failed to update external issue: ' . $e->getMessage());
        }
    }
}
