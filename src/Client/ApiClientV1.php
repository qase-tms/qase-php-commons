<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Client;

use Exception;
use GuzzleHttp\Client;
use Qase\APIClientV1\Api\AttachmentsApi;
use Qase\APIClientV1\Api\EnvironmentsApi;
use Qase\APIClientV1\Api\ProjectsApi;
use Qase\APIClientV1\Api\RunsApi;
use Qase\APIClientV1\Configuration;
use Qase\APIClientV1\Model\RunCreate;
use Qase\PhpCommons\Interfaces\ClientInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Attachment;
use Qase\PhpCommons\Models\Config\TestopsConfig;

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

            foreach ($envs->getResult() as $env) {
                if ($env->getName() == $envName) {
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
    public function createTestRun(string $code, string $title, ?string $description = null, ?int $planId = null, ?int $envId = null): int
    {
        try {
            $this->logger->debug('Create test run: ' . $title);

            $runApi = new RunsApi($this->client, $this->clientConfig);

            $model = new RunCreate();
            $model->setTitle($title);

            if ($description) {
                $model->setDescription($description);
            }

            if ($planId) {
                $model->setPlanId($planId);
            }

            if ($envId) {
                $model->setEnvironmentId($envId);
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

    // TODO: Implement uploadAttachment() method.
    public function uploadAttachment(string $code, Attachment $attachment): ?string
    {
        try {
            $this->logger->debug('Upload attachment: ' . $attachment->getTitle());

            $attachApi = new AttachmentsApi($this->client, $this->clientConfig);
            $attachmentId = $attachApi->uploadAttachment($code, $attachment->getTitle(), $attachment->getMime(), $attachment->getSize(), $attachment->getContent(), $attachment->getPath());

            $this->logger->debug('Attachment uploaded: ' . $attachment->getTitle());

            return $attachmentId->getResult()->getHash();
        } catch (Exception $e) {
            $this->logger->error('Failed to upload attachment: ' . $e->getMessage());
            return null;
        }
    }

    public function sendResults(string $code, int $runId, array $results): void
    {
        // Use Api V2 client for sending results
    }
}
