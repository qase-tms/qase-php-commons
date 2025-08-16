<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;

use Qase\PhpCommons\Client\ApiClientV2;
use Qase\PhpCommons\Config\ConfigLoader;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\ReporterInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Loggers\Logger;
use Qase\PhpCommons\Models\Config\QaseConfig;
use Qase\PhpCommons\Utils\HostInfo;
use Qase\PhpCommons\Utils\StateManager;

class ReporterFactory
{
    public function __construct(
        protected readonly QaseConfig $config,
        protected readonly HostInfo $hostInfo = new HostInfo(),
        protected readonly StateInterface $state = new StateManager(),
        protected LoggerInterface $logger = new Logger(),
    )
    {
    }

    public function createReporter(string $framework = '', string $reporterName = ''): ReporterInterface
    {
        // Set the logger based on the debug mode from the config
        // This is to have exactly the same behavior as before
        if ($this->config->getDebug()){
            $this->logger = new Logger(true);
        }

        $hostData = $this->hostInfo->getHostInfo($framework, $reporterName);
        $this->logger->debug('Host data: ' . json_encode($hostData));
        $reporter = $this->createInternalReporter();
        $fallbackReporter = $this->createInternalReporter(true);

        return new CoreReporter($this->logger, $reporter, $fallbackReporter, $this->config->getRootSuite());
    }

    public static function create(string $framework = '', string $reporterName = ''): ReporterInterface
    {
        $configLoader = new ConfigLoader(new Logger(true));
        $config = $configLoader->getConfig();
        $factory = new self($config);

        return $factory->createReporter($framework, $reporterName);
    }

    protected function createInternalReporter(bool $fallback = false): ?InternalReporterInterface
    {
        $mode = $fallback ? $this->config->getFallback() : $this->config->getMode();

        if ($mode === 'testops') {
            return $this->prepareTestopsReporter();
        }

        if ($mode === 'report') {
            return new FileReporter($this->logger, $this->config->report, $this->state);
        }

        return null;
    }

    protected function prepareTestopsReporter(): InternalReporterInterface
    {
        $client = new ApiClientV2($this->logger, $this->config->testops);
        return new TestOpsReporter($client, $this->config, $this->state);
    }
}
