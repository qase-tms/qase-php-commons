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
use Qase\PhpCommons\Utils\StatusMapping;

class ReporterFactory
{
    public static function create(String $framework = "", String $reporterName = ""): ReporterInterface
    {
        $configLoader = new ConfigLoader(new Logger(true));
        $config = $configLoader->getConfig();
        $logger = new Logger($config->getDebug(), $config->getLogging());
        $hostInfo = new HostInfo();
        $hostData = $hostInfo->getHostInfo($framework, $reporterName);
        $logger->debug("Host data: " . json_encode($hostData));
        $state = new StateManager();
        $reporter = self::createInternalReporter($logger, $config, $state);
        $fallbackReporter = self::createInternalReporter($logger, $config, $state, true);

        // Create status mapping utility
        $statusMapping = new StatusMapping($logger);
        $statusMapping->setMapping($config->getStatusMapping());

        return new CoreReporter($logger, $reporter, $fallbackReporter, $config->getRootSuite(), $statusMapping);
    }

    private static function createInternalReporter(LoggerInterface $logger, QaseConfig $config, StateInterface $state, bool $fallback = false): ?InternalReporterInterface
    {
        $mode = $fallback ? $config->getFallback() : $config->getMode();

        if ($mode === 'testops') {
            return self::prepareTestopsReporter($logger, $config, $state);
        }

        if ($mode === 'report') {
            return new FileReporter($logger, $config->report, $state);
        }

        return null;
    }

    private static function prepareTestopsReporter(LoggerInterface $logger, QaseConfig $config, StateInterface $state): InternalReporterInterface
    {
        $client = new ApiClientV2($logger, $config->testops);
        return new TestOpsReporter($client, $config, $state, $logger);
    }
}
