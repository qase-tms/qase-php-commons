<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Client;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use Qase\APIClientV1\Api\AttachmentsApi;
use Qase\APIClientV1\Api\ConfigurationsApi;
use Qase\APIClientV1\Api\EnvironmentsApi;
use Qase\APIClientV1\Api\ProjectsApi;
use Qase\APIClientV1\Api\RunsApi;
use Qase\APIClientV1\Configuration;
use Qase\APIClientV1\Model\ConfigurationCreate;
use Qase\APIClientV1\Model\ConfigurationGroupCreate;
use Qase\APIClientV1\Model\RunCreate;
use Qase\APIClientV1\Model\RunexternalIssues;
use Qase\APIClientV1\Model\RunexternalIssuesLinksInner;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Attachment;
use Qase\PhpCommons\Models\ConfigurationGroup;
use Qase\PhpCommons\Models\ConfigurationItem;
use Qase\PhpCommons\Models\Config\TestopsConfig;
use SplFileObject;

class ApiClientV1 implements ClientInterface
{
    protected LoggerInterface $logger;
    protected TestopsConfig $config;
    protected Client $client;
    private Configuration $clientConfig;
    private string $appUrl;

    public function __construct(LoggerInterface $logger, TestopsConfig $config)
    {
        $this->logger = $logger;
        $this->config = $config;

        $this->clientConfig = Configuration::getDefaultConfiguration()
            ->setApiKey('Token', $this->config->api->getToken());

        $host = $this->config->api->getHost();
        if ($host == 'qase.io') {
            $this->clientConfig->setHost('https://api.qase.io/v1');
            $this->appUrl = 'https://app.qase.io';
        } else {
            $this->clientConfig->setHost('https://api-' . $host . '/v1');
            $this->appUrl = 'https://' . $host;
        }
        $this->client = new Client();
    }

    public function isProjectExist(string $code): bool
    {
        try {
            $this->logger->debug('Check project exist: ' . $code);

            $projectsApi = new ProjectsApi($this->client, $this->clientConfig);
            $project = $projectsApi->getProject($code);

            $result = $project->getStatus() == 200;

            if (!$result) {
                $this->logger->debug('Project not found: ' . $code);
                return false;
            }

            $this->logger->debug('Project found: ' . $code);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to check project exist: ' . $e->getMessage());
            return false;
        }
    }

    public function getEnvironment(string $code, string $envName): ?int
    {
        try {
            $this->logger->debug('Get environment: ' . $envName);

            $envApi = new EnvironmentsApi($this->client, $this->clientConfig);
            $envs = $envApi->getEnvironments($code, null, $envName, 100);

            foreach ($envs->getResult()->getEntities() as $env) {
                if ($env->getSlug() == $envName) {
                    $this->logger->debug('Environment found: ' . $envName);
                    return $env->getId();
                }
            }

            $this->logger->debug('Environment not found: ' . $envName);

            return null;
        } catch (Exception $e) {
            $this->logger->error('Failed to get environment: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @throws Exception
     */
    public function createTestRun(string $code, string $title, ?string $description = null, ?int $planId = null, ?int $envId = null, ?array $tags = null, ?array $configurations = null): int
    {
        try {
            $this->logger->debug('Create test run: ' . $title);

            $runApi = new RunsApi($this->client, $this->clientConfig);

            $model = new RunCreate();
            $model->setTitle($title);
            $model->setIsAutotest(true);
            $model->setStartTime((new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'));

            if ($description) {
                $model->setDescription($description);
            }

            if ($planId) {
                $model->setPlanId($planId);
            }

            if ($envId) {
                $model->setEnvironmentId($envId);
            }

            if ($tags) {
                $model->setTags($tags);
            }

            if ($configurations) {
                $model->setConfigurations($configurations);
            }

            $run = $runApi->createRun($code, $model);
            $id = $run->getResult()->getId();

            $this->logger->info('Test run created with id: ' . $id);

            return $id;
        } catch (Exception $e) {
            throw new Exception('Failed to create test run: ' . $e->getMessage());
        }
    }

    public function completeTestRun(string $code, int $runId): void
    {
        try {
            $this->logger->debug('Complete test run: ' . $runId);

            $runApi = new RunsApi($this->client, $this->clientConfig);
            $runApi->completeRun($code, $runId);

            $this->logger->info('Test run link: ' . $this->appUrl . '/run/' . $code . '/dashboard/' . $runId);
        } catch (Exception $e) {
            $this->logger->error('Failed to complete test run: ' . $e->getMessage());
        }
    }

    public function isTestRunExist(string $code, int $runId): bool
    {
        try {
            $this->logger->debug('Check test run exist: ' . $runId);

            $runApi = new RunsApi($this->client, $this->clientConfig);
            $run = $runApi->getRun($code, $runId);

            $result = $run->getStatus() == 200;

            if (!$result) {
                $this->logger->debug('Test run not found: ' . $runId);
                return false;
            }

            $this->logger->debug('Test run found: ' . $runId);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to check test run exist: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload one or multiple attachments
     * 
     * Limitations:
     * - Up to 32 MB per file
     * - Up to 128 MB per single request
     * - Up to 20 files per single request
     * 
     * @param string $code Project code
     * @param Attachment|Attachment[] $attachments Single attachment or array of attachments
     * @return string|string[]|null Hash(es) of uploaded attachment(s) or null on failure
     */
    public function uploadAttachment(string $code, Attachment|array $attachments): string|array|null
    {
        // Normalize to array
        $attachmentsArray = is_array($attachments) ? $attachments : [$attachments];
        
        // Check for empty array
        if (empty($attachmentsArray)) {
            $this->logger->warning('Empty attachments array provided');
            return is_array($attachments) ? [] : null;
        }
        
        try {
            $this->logger->debug('Upload ' . count($attachmentsArray) . ' attachment(s)');

            // Filter and validate individual file constraints (skip invalid files)
            $validAttachments = $this->filterValidAttachments($attachmentsArray);
            
            if (empty($validAttachments)) {
                $this->logger->warning('No valid attachments to upload after filtering');
                return is_array($attachments) ? [] : null;
            }

            // Split into batches
            $batches = $this->splitIntoBatches($validAttachments);
            $this->logger->debug('Split into ' . count($batches) . ' batch(es)');

            $allHashes = [];
            $attachApi = new AttachmentsApi($this->client, $this->clientConfig);

            // Upload each batch
            foreach ($batches as $batchIndex => $batch) {
                $this->logger->debug('Uploading batch ' . ($batchIndex + 1) . '/' . count($batches) . ' with ' . count($batch) . ' file(s)');
                
                $fileObjects = [];
                $tempFiles = [];

                // Prepare files for this batch
                foreach ($batch as $attachment) {
                    if ($attachment->path) {
                        $fileObjects[] = new SplFileObject($attachment->path);
                    } elseif ($attachment->content) {
                        $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('qase_attach_', true) . '_' . ($attachment->title ?? 'attachment');
                        if (file_put_contents($filepath, $attachment->content) === false) {
                            $this->logger->error('Can not save attachment: ' . $filepath);
                            // Clean up already created temp files
                            foreach ($tempFiles as $tempFile) {
                                if (file_exists($tempFile)) {
                                    unlink($tempFile);
                                }
                            }
                            return null;
                        }
                        $fileObjects[] = new SplFileObject($filepath);
                        $tempFiles[] = $filepath;
                    } else {
                        $this->logger->error('Attachment has neither path nor content');
                        // Clean up temp files
                        foreach ($tempFiles as $tempFile) {
                            if (file_exists($tempFile)) {
                                unlink($tempFile);
                            }
                        }
                        return null;
                    }
                }

                // Upload batch
                $result = $attachApi->uploadAttachment($code, $fileObjects);

                // Clean up temp files for this batch
                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile) && unlink($tempFile) === false) {
                        $this->logger->error('Can not remove temporary attachment: ' . $tempFile);
                    }
                }

                // Extract hashes from result
                if ($result && $result->getResult()) {
                    foreach ($result->getResult() as $attachmentResult) {
                        $allHashes[] = $attachmentResult->getHash();
                    }
                }
            }

            // Return single hash if single attachment was provided, array otherwise
            if (!is_array($attachments)) {
                return $allHashes[0] ?? null;
            }

            return $allHashes;
        } catch (Exception $e) {
            $this->logger->error('Failed to upload attachment(s): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Filter attachments and return only valid ones, logging errors for invalid files
     * 
     * @param Attachment[] $attachments
     * @return Attachment[] Array of valid attachments
     */
    private function filterValidAttachments(array $attachments): array
    {
        $maxFileSize = 32 * 1024 * 1024; // 32 MB
        $validAttachments = [];

        foreach ($attachments as $index => $attachment) {
            $fileSize = 0;
            $fileName = $attachment->title ?? $attachment->path ?? "attachment at index {$index}";

            // Check if file has path or content
            if ($attachment->path) {
                if (!file_exists($attachment->path)) {
                    $this->logger->warning("Skipping attachment '{$fileName}': file not found: {$attachment->path}");
                    continue;
                }
                $fileSize = filesize($attachment->path);
            } elseif ($attachment->content) {
                $fileSize = strlen($attachment->content);
            } else {
                $this->logger->warning("Skipping attachment '{$fileName}': has neither path nor content");
                continue;
            }

            // Check file size
            if ($fileSize > $maxFileSize) {
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                $this->logger->warning("Skipping attachment '{$fileName}': file size {$fileSizeMB} MB exceeds maximum of 32 MB per file");
                continue;
            }

            $validAttachments[] = $attachment;
        }

        $skippedCount = count($attachments) - count($validAttachments);
        if ($skippedCount > 0) {
            $this->logger->info("Filtered out {$skippedCount} invalid attachment(s), proceeding with " . count($validAttachments) . " valid attachment(s)");
        }

        return $validAttachments;
    }

    /**
     * Split attachments into batches respecting API constraints
     * 
     * @param Attachment[] $attachments
     * @return Attachment[][] Array of batches
     */
    private function splitIntoBatches(array $attachments): array
    {
        $maxFilesPerBatch = 20;
        $maxSizePerBatch = 128 * 1024 * 1024; // 128 MB

        $batches = [];
        $currentBatch = [];
        $currentBatchSize = 0;

        foreach ($attachments as $attachment) {
            // Get file size (files should be validated already, but add safety check)
            $fileSize = 0;
            if ($attachment->path) {
                if (file_exists($attachment->path)) {
                    $fileSize = filesize($attachment->path);
                } else {
                    $this->logger->warning("Skipping attachment in batch: file not found: {$attachment->path}");
                    continue;
                }
            } elseif ($attachment->content) {
                $fileSize = strlen($attachment->content);
            } else {
                $this->logger->warning("Skipping attachment in batch: has neither path nor content");
                continue;
            }

            // Check if we need to start a new batch
            $wouldExceedFileLimit = count($currentBatch) >= $maxFilesPerBatch;
            $wouldExceedSizeLimit = ($currentBatchSize + $fileSize) > $maxSizePerBatch;

            if ($wouldExceedFileLimit || $wouldExceedSizeLimit) {
                // Save current batch and start a new one
                if (!empty($currentBatch)) {
                    $batches[] = $currentBatch;
                    $currentBatch = [];
                    $currentBatchSize = 0;
                }
            }

            // Add file to current batch
            $currentBatch[] = $attachment;
            $currentBatchSize += $fileSize;
        }

        // Add the last batch if it's not empty
        if (!empty($currentBatch)) {
            $batches[] = $currentBatch;
        }

        return $batches;
    }

    public function sendResults(string $code, int $runId, array $results): void
    {
        // Use Api V2 client for sending results
    }

    public function getConfigurationGroups(string $code): array
    {
        try {
            $this->logger->debug('Get configuration groups for project: ' . $code);

            $configApi = new ConfigurationsApi($this->client, $this->clientConfig);
            $groups = $configApi->getConfigurations($code);

            $result = [];
            if ($groups && $groups->getResult() && $groups->getResult()->getEntities()) {
                foreach ($groups->getResult()->getEntities() as $group) {
                    $configGroup = new ConfigurationGroup(
                        $group->getId(),
                        $group->getTitle()
                    );
                    
                    // Store items for this group if they exist
                    if ($group->getConfigurations()) {
                        foreach ($group->getConfigurations() as $item) {
                            $configGroup->items[] = new ConfigurationItem(
                                $item->getId(),
                                $item->getTitle()
                            );
                        }
                    }
                    
                    $result[] = $configGroup;
                }
            }

            $this->logger->debug('Found ' . count($result) . ' configuration groups');
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to get configuration groups: ' . $e->getMessage());
            return [];
        }
    }

    public function createConfigurationGroup(string $code, string $title): ?ConfigurationGroup
    {
        try {
            $this->logger->debug('Create configuration group: ' . $title);

            $configApi = new ConfigurationsApi($this->client, $this->clientConfig);

            $model = new ConfigurationGroupCreate();
            $model->setTitle($title);

            $group = $configApi->createConfigurationGroup($code, $model);
            $result = new ConfigurationGroup(
                $group->getResult()->getId(),
                $title
            );

            $this->logger->debug('Configuration group created with id: ' . $result->getId());
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to create configuration group: ' . $e->getMessage());
            return null;
        }
    }



    public function createConfigurationItem(string $code, int $groupId, string $title): ?ConfigurationItem
    {
        try {
            $this->logger->debug('Create configuration item: ' . $title . ' in group: ' . $groupId);

            $configApi = new ConfigurationsApi($this->client, $this->clientConfig);

            $model = new ConfigurationCreate();
            $model->setTitle($title);
            $model->setGroupId($groupId);

            $item = $configApi->createConfiguration($code, $model);
            $result = new ConfigurationItem(
                $item->getResult()->getId(),
                $title
            );

            $this->logger->debug('Configuration item created with id: ' . $result->getId());
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to create configuration item: ' . $e->getMessage());
            return null;
        }
    }

    public function runUpdateExternalIssue(string $code, string $type, array $links): void
    {
        try {
            $this->logger->debug('Update external issue for project: ' . $code . ', type: ' . $type);

            // Map our enum values to API enum values
            $apiType = $type === 'jiraCloud' ? RunexternalIssues::TYPE_JIRA_CLOUD : RunexternalIssues::TYPE_JIRA_SERVER;

            // Create links array using API models
            $apiLinks = [];
            foreach ($links as $link) {
                $linkModel = new RunexternalIssuesLinksInner();
                $linkModel->setRunId($link['run_id']);
                $linkModel->setExternalIssue($link['external_issue']);
                $apiLinks[] = $linkModel;
            }

            // Create the request model
            $runExternalIssues = new RunexternalIssues();
            $runExternalIssues->setType($apiType);
            $runExternalIssues->setLinks($apiLinks);

            $this->logger->debug('External issue update request: ' . json_encode($runExternalIssues));

            // Use the API client
            $runApi = new RunsApi($this->client, $this->clientConfig);
            $runApi->runUpdateExternalIssue($code, $runExternalIssues);

            $this->logger->info('External issue updated successfully');
        } catch (Exception $e) {
            $this->logger->error('Failed to update external issue: ' . $e->getMessage());
        }
    }

    public function enablePublicReport(string $code, int $runId): ?string
    {
        try {
            $this->logger->debug('Enable public report for run: ' . $runId);

            // Make PATCH request to enable public report
            $response = $this->client->request('PATCH', $this->clientConfig->getHost() . '/run/' . $code . '/' . $runId . '/public', [
                'headers' => [
                    'Token' => $this->config->api->getToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'status' => true,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (isset($responseData['result']['hash'])) {
                $publicUrl = $this->appUrl . '/public/report/' . $responseData['result']['hash'];
                $this->logger->info('Public report link: ' . $publicUrl);
                return $publicUrl;
            }

            $this->logger->warning('Public report hash not found in response');
            return null;
        } catch (Exception $e) {
            $this->logger->warning('Failed to generate public report link: ' . $e->getMessage());
            return null;
        }
    }
}
