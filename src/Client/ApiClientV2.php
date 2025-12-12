<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Client;

use Exception;
use GuzzleHttp\Client;
use Qase\APIClientV2\Api\ResultsApi;
use Qase\APIClientV2\Configuration;
use Qase\APIClientV2\Model\CreateResultsRequestV2;
use Qase\APIClientV2\Model\RelationSuite;
use Qase\APIClientV2\Model\RelationSuiteItem;
use Qase\APIClientV2\Model\ResultCreate;
use Qase\APIClientV2\Model\ResultCreateFields;
use Qase\APIClientV2\Model\ResultExecution;
use Qase\APIClientV2\Model\ResultRelations;
use Qase\APIClientV2\Model\ResultStep;
use Qase\APIClientV2\Model\ResultStepData;
use Qase\APIClientV2\Model\ResultStepExecution;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Config\TestopsConfig;
use Qase\PhpCommons\Models\Relation;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Models\Step;
use Qase\PhpCommons\Utils\HostInfo;

class ApiClientV2 extends ApiClientV1
{
    private Configuration $clientV2Config;
    private Client $clientV2;

    public function __construct(LoggerInterface $logger, TestopsConfig $config, string $framework = "", string $reporterName = "", array $hostData = [])
    {
        parent::__construct($logger, $config);

        $this->clientV2Config = Configuration::getDefaultConfiguration()
            ->setApiKey('Token', $this->config->api->getToken());

        $host = $this->config->api->getHost();
        if ($host == 'qase.io') {
            $this->clientV2Config->setHost('https://api.qase.io/v2');
        } else {
            $this->clientV2Config->setHost('https://api-' . $host . '/v2');
        }

        // Create GuzzleHttp Client with default headers
        $headers = $this->buildHeaders($framework, $reporterName, $hostData);
        $this->clientV2 = new Client([
            'headers' => $headers
        ]);
    }

    public function sendResults(string $code, int $runId, array $results): void
    {
        try {
            $this->logger->debug('Send ' . count($results) . ' results to project: ' . $code . ', run: ' . $runId);

            $model = new CreateResultsRequestV2();
            $convertedResults = [];
            foreach ($results as $result) {
                $convertedResults[] = $this->covertToModel($result);
            }
            $model->setResults($convertedResults);

            $this->logger->debug("Send results to project: " . json_encode($model));

            $resultsApi = new ResultsApi($this->clientV2, $this->clientV2Config);
            $resultsApi->createResultsV2($code, $runId, $model);
        } catch (Exception $e) {
            $this->logger->error("Error send results to project: " . $code . ', run: ' . $runId);
            $this->logger->error($e->getMessage());
        }
    }

    private function covertToModel(Result $result): ResultCreate
    {
        $model = new ResultCreate();
        $model->setTitle($result->title);
        $model->setId($result->id);
        $model->setSignature($result->signature);
        $model->setTestOpsIds($result->testOpsIds);

        $execution = new ResultExecution();
        $execution->setStatus($result->execution->getStatus());
        $execution->setStartTime($result->execution->getStartTime());
        $execution->setEndTime($result->execution->getEndTime());
        $execution->setDuration($result->execution->getDuration());
        $execution->setStacktrace($result->execution->getStackTrace());
        $execution->setThread($result->execution->getThread());
        $model->setExecution($execution);

        $fields = new ResultCreateFields($result->fields);
        $model->setFields($fields);

        $model->setAttachments($this->convertAttachments($result->attachments));
        $model->setParams($result->params);
        $model->setParamGroups($result->groupParams);
        $model->setMessage($result->message);
        $model->setRelations($this->convertRelations($result->relations));
        $model->setDefect($this->config->isDefect());

        $steps = [];
        foreach ($result->steps as $step) {
            $steps[] = $this->convertStep($step);
        }
        $model->setSteps($steps);

        $this->logger->debug("Convert result to model: " . json_encode($model));

        return $model;
    }

    private function convertStep(Step $step): ResultStep
    {
        $model = new ResultStep();

        $dataModel = new ResultStepData();
        $dataModel->setAction($step->data->getAction());
        $model->setData($dataModel);

        $executionModel = new ResultStepExecution();
        $executionModel->setStatus($step->execution->getStatus());
        $executionModel->setDuration($step->execution->getDuration());
        $executionModel->setAttachments($this->convertAttachments($step->attachments));
        $model->setExecution($executionModel);

        $steps = [];
        foreach ($step->steps as $step) {
            $steps[] = $this->convertStep($step);
        }
        $model->setSteps($steps);

        return $model;
    }

    private function convertRelations(Relation $relation): ResultRelations
    {
        $model = new ResultRelations();

        $suite = new RelationSuite();

        $data = [];
        foreach ($relation->suite->data as $item) {
            $suiteItem = new RelationSuiteItem();
            $suiteItem->setTitle($item->title);
            $data[] = $suiteItem;
        }

        $suite->setData($data);
        $model->setSuite($suite);

        return $model;
    }

    private function convertAttachments(array $attachments): array
    {
        if (empty($attachments)) {
            return [];
        }

        // Upload all attachments at once (method handles validation and batching if needed)
        $result = $this->uploadAttachment($this->config->getProject(), $attachments);
        
        if (is_array($result)) {
            return $result;
        } elseif (is_string($result)) {
            return [$result];
        }

        return [];
    }

    public function runUpdateExternalIssue(string $code, string $type, array $links): void
    {
        // External issue functionality is only available in API v1
        // Delegate to parent class (ApiClientV1) implementation
        parent::runUpdateExternalIssue($code, $type, $links);
    }

    /**
     * Build X-Client and X-Platform headers based on HostInfo data
     *
     * @param string $framework Framework name
     * @param string $reporterName Reporter name
     * @param array $hostData Host data from HostInfo
     * @return array Headers array
     */
    private function buildHeaders(string $framework = "", string $reporterName = "", array $hostData = []): array
    {
        $headers = [];

        // If hostData is empty, try to get it from HostInfo (fallback for backward compatibility)
        if (empty($hostData)) {
            $hostInfo = new HostInfo();
            $hostData = $hostInfo->getHostInfo($framework, $reporterName);
        }

        // Build X-Client header
        $xClientParts = [];
        
        if (!empty($reporterName)) {
            $xClientParts[] = 'reporter=' . $reporterName;
        }
        
        if (!empty($hostData['reporter'])) {
            $reporterVersion = $this->normalizeVersion($hostData['reporter']);
            if (!empty($reporterVersion)) {
                $xClientParts[] = 'reporter_version=v' . $reporterVersion;
            }
        }
        
        if (!empty($framework)) {
            $xClientParts[] = 'framework=' . $framework;
        }
        
        if (!empty($hostData['framework'])) {
            $frameworkVersion = $this->normalizeVersion($hostData['framework']);
            if (!empty($frameworkVersion)) {
                $xClientParts[] = 'framework_version=v' . $frameworkVersion;
            }
        }
        
        if (!empty($hostData['apiClientV1'])) {
            $clientV1Version = $this->normalizeVersion($hostData['apiClientV1']);
            if (!empty($clientV1Version)) {
                $xClientParts[] = 'client_version_v1=v' . $clientV1Version;
            }
        }
        
        if (!empty($hostData['apiClientV2'])) {
            $clientV2Version = $this->normalizeVersion($hostData['apiClientV2']);
            if (!empty($clientV2Version)) {
                $xClientParts[] = 'client_version_v2=v' . $clientV2Version;
            }
        }
        
        if (!empty($hostData['commons'])) {
            $commonsVersion = $this->normalizeVersion($hostData['commons']);
            if (!empty($commonsVersion)) {
                $xClientParts[] = 'core_version=v' . $commonsVersion;
            }
        }

        if (!empty($xClientParts)) {
            $headers['X-Client'] = implode(';', $xClientParts);
        }

        // Build X-Platform header
        $xPlatformParts = [];
        
        if (!empty($hostData['system'])) {
            $osName = ucfirst($hostData['system']);
            $xPlatformParts[] = 'os=' . $osName;
        }
        
        if (!empty($hostData['arch'])) {
            $xPlatformParts[] = 'arch=' . $hostData['arch'];
        }
        
        if (!empty($hostData['php'])) {
            $xPlatformParts[] = 'php=' . $hostData['php'];
        }
        
        if (!empty($hostData['composer'])) {
            $xPlatformParts[] = 'composer=' . $hostData['composer'];
        }

        if (!empty($xPlatformParts)) {
            $headers['X-Platform'] = implode(';', $xPlatformParts);
        }

        return $headers;
    }

    /**
     * Normalize version string by removing constraints and prefixes
     *
     * @param string $version Version string from composer.json/composer.lock
     * @return string Normalized version (e.g., "1.0.0" from "^1.0.0" or "v1.0.0")
     */
    private function normalizeVersion(string $version): string
    {
        if (empty($version)) {
            return '';
        }

        // Remove version constraints (^, ~, >=, etc.)
        $version = preg_replace('/^[^0-9]*/', '', $version);
        
        // Remove 'v' prefix if present
        $version = ltrim($version, 'v');
        
        // Extract version number (e.g., "1.0.0" from "1.0.0.0" or "1.0.0-dev")
        if (preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)) {
            return $matches[1];
        }
        
        // If no match, try to extract at least major.minor
        if (preg_match('/^(\d+\.\d+)/', $version, $matches)) {
            return $matches[1] . '.0';
        }
        
        // If still no match, try to extract at least major
        if (preg_match('/^(\d+)/', $version, $matches)) {
            return $matches[1] . '.0.0';
        }

        return '';
    }
}
