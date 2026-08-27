<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;

use Exception;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;
use Throwable;

class TestOpsReporter implements InternalReporterInterface
{
    private array $results = [];
    private ClientInterface $client;
    private QaseConfig $config;
    private StateInterface $state;
    private LoggerInterface $logger;
    private ?int $runId = null;
    private ?array $cachedConfigurationGroups = null;
    private bool $deferFlush = false;
    private ?Throwable $lastSendError = null;

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
                
                // Enable public report if configured
                if ($this->config->testops->isShowPublicReportLink()) {
                    $this->enablePublicReportForRun();
                }
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

        // A batch has already failed: keep buffering and try again once the run
        // is finished, instead of running the retry ladder on every new result.
        if ($this->deferFlush) {
            return;
        }

        if (count($this->results) < $this->getBatchSize()) {
            return;
        }

        if (!$this->flushResults()) {
            $this->deferFlush = true;
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

    private function getEnvironmentId(?string $name): ?int
    {
        return $name ? $this->client->getEnvironment($this->config->testops->getProject(), $name) : null;
    }

    /**
     * Send everything that is still buffered.
     *
     * @throws Exception When a batch could not be delivered. The undelivered
     *                   results stay in the buffer so that the fallback
     *                   reporter can persist them and the run is left open.
     */
    public function sendResults(): void
    {
        while (!empty($this->results)) {
            if (!$this->flushResults()) {
                $lost = count($this->results);
                $message = sprintf(
                    'Failed to send %d test result(s) to Qase: the batch was not confirmed by the server.'
                    . ' The results are kept for the fallback reporter and the test run will not be completed.',
                    $lost
                );
                $this->logger->error($message);

                throw new Exception($message, 0, $this->lastSendError);
            }
        }

        $this->deferFlush = false;
    }

    /**
     * Send the head of the buffer.
     *
     * The chunk is only removed once the server has confirmed it: an
     * undelivered batch must stay owned by the reporter, otherwise the results
     * are destroyed by the very failure they need to survive.
     *
     * @return bool True when the batch was accepted by the server
     */
    private function flushResults(): bool
    {
        $chunk = array_slice($this->results, 0, $this->getBatchSize());

        try {
            $this->sendResultsByBatch($chunk);
        } catch (Throwable $e) {
            $this->lastSendError = $e;
            $this->logger->warning(sprintf(
                'Failed to send a batch of %d test result(s): %s. The results are kept in the buffer.',
                count($chunk),
                $e->getMessage()
            ));

            return false;
        }

        array_splice($this->results, 0, count($chunk));

        return true;
    }

    /**
     * Batch size, never below 1: a zero size would make the send loop spin forever.
     */
    private function getBatchSize(): int
    {
        return max(1, $this->config->testops->batch->getSize());
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

    /**
     * Enable public report for the current test run
     */
    private function enablePublicReportForRun(): void
    {
        try {
            $publicUrl = $this->client->enablePublicReport(
                $this->config->testops->getProject(),
                $this->runId
            );
            
            // Logger already prints the link inside the client method
            // No need to print again here
        } catch (Exception $e) {
            // Log warning through the centralized logger
            $this->logger->warning('Failed to enable public report: ' . $e->getMessage());
        }
    }
}
