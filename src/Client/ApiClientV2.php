<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Client;

use Exception;
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

class ApiClientV2 extends ApiClientV1
{
    private Configuration $clientV2Config;

    public function __construct(LoggerInterface $logger, TestopsConfig $config)
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

            $resultsApi = new ResultsApi($this->client, $this->clientV2Config);
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
        $model->setTestOpsId($result->testOpsId);

        $execution = new ResultExecution();
        $execution->setStatus($result->execution->getStatus());
        $execution->setDuration($result->execution->getDuration());
        $execution->setStacktrace($result->execution->getStackTrace());
        $execution->setThread($result->execution->getThread());
        $model->setExecution($execution);

        $fields = new ResultCreateFields($result->fields);
        $model->setFields($fields);

        $model->setAttachments($result->attachments);
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
        $executionModel->setAttachments($step->attachments);
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
}
