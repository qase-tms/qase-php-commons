<?php

declare(strict_types=1);

namespace Qase\PhpClientUtils;

use Qase\APIClientV1\ApiException;
use Qase\APIClientV1\Model\Response;
use Qase\APIClientV1\Model\ResultCreateBulk;
use Qase\APIClientV1\Model\RunCreate;

class ResultHandler
{

    private LoggerInterface $logger;
    private Repository $repo;
    private ResultsConverter $resultsConverter;

    public function __construct(Repository $repo, ResultsConverter $resultsConverter, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->repo = $repo;
        $this->resultsConverter = $resultsConverter;
    }

    /**
     * @throws ApiException
     */
    public function handle(RunResult $runResult, string $rootSuiteTitle): ?Response
    {
        $this->logger->writeln('', '');
        $this->logger->writeln('Results handling started');

        $bulkResults = $this->resultsConverter->prepareBulkResults($runResult, $rootSuiteTitle);

        if ($bulkResults === []) {
            $this->logger->writeln('WARNING: did not find any tests to report in the Qase TMS');
            return null;
        }

        return $this->submit($runResult, $bulkResults);
    }

    /**
     * @throws ApiException
     */
    private function submit(RunResult $runResult, array $bulkResults): Response
    {
        $runId = $runResult->getConfig()->getRunId() ?: $this->createRunId($runResult);

        $this->logger->write("publishing results for run #{$runId}... ");

        $response = $this->repo->getResultsApi()->createResultBulk(
            $runResult->getConfig()->getProjectCode(),
            $runId,
            new ResultCreateBulk(['results' => $bulkResults])
        );

        $this->logger->writeln('OK', '');

        if ($runResult->getConfig()->getCompleteRunAfterSubmit()) {
            $this->logger->write("completing run #{$runId}... ");

            $this->repo->getRunsApi()->completeRun($runResult->getConfig()->getProjectCode(), $runId);

            $this->logger->writeln('OK', '');
        }

        return $response;
    }

    /**
     * @throws ApiException
     */
    private function createRunId(RunResult $runResult): int
    {
        $runName = $runResult->getConfig()->getRunName() ?: 'Automated run ' . date('Y-m-d H:i:s');
        $runBody = new RunCreate([
            "title" => $runName,
            "description" => $runResult->getConfig()->getRunDescription(),
            'isAutotest' => true,
            'environmentId' => $runResult->getConfig()->getEnvironmentId(),
        ]);

        $this->logger->write("creating run '{$runName}'... ");

        $response = $this->repo->getRunsApi()->createRun($runResult->getConfig()->getProjectCode(), $runBody);

        if ($response->getResult() === null) {
            throw new \RuntimeException('Could not create run');
        }

        $this->logger->writeln('OK', '');

        return $response->getResult()->getId();
    }
}
