<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Config;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Utils\HeaderManager;

class TestOpsConfig extends BaseConfig
{
    public const REQUIRED_PARAMS = [
        'QASE_TO_PROJECT_CODE',
        'QASE_TO_API_TOKEN',
    ];
    private string $baseUrl = "https://api.qase.io/v1/";
    private string $apiToken;

    private string $mode = 'async';
    private ?int $runId;
    private ?string $runTitle;
    private ?string $runDescription;
    private ?int $environmentId;
    private ?int $planId;
    private bool $isLoggingEnabled;
    private ?string $rootSuiteTitle;
    private bool $completeRunAfterSubmit;
    private string $projectCode;
    private HeaderManager $headerManager;

    public function __construct(string $reporterName, LoggerInterface $logger, HeaderManager $headerManager)
    {
        parent::__construct($reporterName, $logger);

        $this->validate();
        $this->baseUrl = getenv('QASE_TO_HOST') ?: "https://api.qase.io/v1/";

        $this->headerManager = $headerManager;

        $this->apiToken = getenv('QASE_TO_API_TOKEN');
        $this->projectCode = getenv('QASE_TO_PROJECT_CODE');
        $this->mode = getenv('QASE_TO_MODE') ?: 'async';

        $this->rootSuiteTitle = getenv('QASE_TO_ROOT_SUITE_TITLE') ?: null;
        $this->planId = getenv('QASE_TO_PLAN_ID') ? (int)getenv('QASE_TO_PLAN_ID') : null;
        $this->runId = getenv('QASE_TO_RUN_ID') ? (int)getenv('QASE_TO_RUN_ID') : null;
        $this->runTitle = getenv('QASE_TO_RUN_TITLE') ? getenv('QASE_TO_RUN_TITLE') : null;
        $this->runDescription = getenv('QASE_TO_RUN_DESCRIPTION') ? getenv('QASE_TO_RUN_DESCRIPTION') : null;
        $this->completeRunAfterSubmit = getenv('QASE_TO_RUN_COMPLETE') === 'true' 
            || getenv('QASE_TO_RUN_COMPLETE') === '1' 
            || getenv('QASE_TO_RUN_COMPLETE') === false;

        $this->environmentId = getenv('QASE_TO_ENVIRONMENT_ID') ? (int)getenv('QASE_TO_ENVIRONMENT_ID') : null;
    }

    public function getProjectCode(): string
    {
        return $this->projectCode;
    }

    public function getEnvironmentId(): ?int
    {
        return $this->environmentId;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function getRunId(): ?int
    {
        return $this->runId;
    }

    public function getPlanId(): ?int
    {
        return $this->planId;
    }

    public function getRunTitle(): string
    {
        return $this->runTitle ?: "{$this->reporterName} automated run";
    }

    public function getRunDescription(): string
    {
        return $this->runDescription ?: "{$this->reporterName} automated run";
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setRunId(int $runId): void
    {
        $this->runId = $runId;
    }

    public function getCompleteRunAfterSubmit(): bool
    {
        return $this->completeRunAfterSubmit;
    }

    public function getRootSuiteTitle(): ?string
    {
        return $this->rootSuiteTitle;
    }

    public function getHeaders(): array
    {
        return $this->headerManager->getClientHeaders();
    }

}