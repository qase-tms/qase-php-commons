<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;
use Qase\Client\Api\EnvironmentsApi;
use Qase\Client\Api\ProjectsApi;
use Qase\Client\Api\ResultsApi;
use Qase\Client\Api\RunsApi;
use Qase\Client\Model\ResultCreate;
use Qase\Client\Model\RunCreate;
use Qase\PhpCommons\Config\TestOpsConfig;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Interfaces\ReporterInterface;
use Qase\PhpCommons\Collections\ResultCollection;
use Qase\Client\Configuration;

class TestOps implements ReporterInterface
{
    private ResultCollection $results;

    protected string $mode = 'async';

    protected $runsApi;

    protected $projectsApi;

    protected $environmentsApi;

    protected $resultsApi;

    private TestOpsConfig $config;

    public function __construct(TestOpsConfig $config) 
    {
        $this->config = $config;
        $this->mode = 'async';
        $this->results = new ResultCollection();

        $config = Configuration::getDefaultConfiguration()
            ->setHost($this->config->getBaseUrl())
            ->setApiKey('Token', $this->config->getApiToken());

        $client = new \GuzzleHttp\Client([
            'headers' => array_merge(
                ['Content-Type' => 'application/json'],
                $this->config->getHeaders()
            ),
        ]);

        $this->runsApi = new RunsApi($client, $config);
        $this->projectsApi = new ProjectsApi($client, $config);
        $this->environmentsApi = new EnvironmentsApi($client, $config);
        $this->resultsApi = new ResultsApi($client, $config);
    }

    public function startRun(): void
    {
        if (!$this->config->getRunId()) {
            $data = [
                'title' => $this->config->getRunTitle(),
                'description' => $this->config->getRunDescription(),
                'isAutotest' => true,
            ];
    
            if ($this->config->getEnvironmentId()) {
                $data['environmentId'] = $this->config->getEnvironmentId();
            }
            if ($this->config->getPlanId()) {
                $data['planId'] = $this->config->getPlanId();
            }
            $run = $this->runsApi->createRun('PHP', new RunCreate($data));
    
            $this->config->setRunId($run->getResult()->getId());
        }
    }

    public function completeRun(): void
    {
        if ($this->config->getMode() === 'async') {
            $this->sendBulkResults();
        }
        if ($this->config->getCompleteRunAfterSubmit()) {
            $this->config->logger->write("Completing Test Run ID:'{$this->config->getRunId()}'.");
            $this->runsApi->completeRun('PHP', $this->config->getRunId());
        }
    }

    public function addResult(Result $result): void
    {
        if ($this->config->getMode() === 'async') {
            $this->results->addResult($result);
        } else {
            $this->sendResult($result);
        }
    }

    private function sendResult(Result $result): void
    {
        $runId = $this->config->getRunId();

        $resultCreate = new ResultCreate([
            'case_id' => $result->testOpsId,
            'case' => [
                'title' => $result->title,
                'description' => $result->description,
                'suite_title' => $result->suite,
                'automation' => 2,
                'layer' => 'unit'
            ],
            'timeMs' => $result->duration,
            'status' => $result->status,
            'comment' => $result->comment,
            'steps' => []
        ]);

        $this->config->logger->write("Sending result [{$result->title}] to project '{$this->config->getProjectCode()}'\n");

        $this->resultsApi->createResult('PHP', $runId, $resultCreate);
    }

    private function sendBulkResults(): void
    {
        $runId = $this->config->getRunId();
        $results = $this->results->getResults();
        $resultsBulk = [];

        foreach ($results as $result) {
            $resultsBulk[] = new ResultCreate([
                'case_id' => $result->testOpsId,
                'case' => [
                    'title' => $result->title,
                    'description' => $result->description,
                    'suite_title' => $result->suite,
                    'automation' => 2,
                    'layer' => 'unit'
                ],
                'timeMs' => $result->duration,
                'status' => $result->status,
                'comment' => $result->comment,
                'steps' => []
            ]);
        }

        $resultsCreateBulk = new \Qase\Client\Model\ResultCreateBulk(['results' => $resultsBulk]);

        $this->config->logger->write("Sending '{$this->results->getCount()}' results to project '{$this->config->getProjectCode()}'");

        $this->resultsApi->createResultBulk('PHP', $runId, $resultsCreateBulk);
    }
}