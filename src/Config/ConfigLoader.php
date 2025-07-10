<?php

namespace Qase\PhpCommons\Config;

use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;
use RuntimeException;

class ConfigLoader
{
    private QaseConfig $config;
    private string $filePath = '/qase.config.json';

    public function __construct(LoggerInterface $logger)
    {
        $this->config = $this->loadFromJsonFile();
        $this->overrideWithEnvVariables();

        $logger->debug("Loaded configuration: " . json_encode($this->config));

        $this->validate();
    }

    public function getConfig(): QaseConfig
    {
        return $this->config;
    }

    private function validate(): void
    {
        $mode = $this->config->getMode();
        $token = $this->config->testops->api->getToken();
        $project = $this->config->testops->getProject();

        if ($mode === "testops" && (empty($token) || empty($project))) {
            throw new RuntimeException("TestOps mode requires API token and project to be set");
        }
    }

    private function loadFromJsonFile(): QaseConfig
    {
        $config = new QaseConfig();
        $configFilePath = getcwd() . $this->filePath;

        if (!file_exists($configFilePath)) {
            return $config;
        }

        $jsonData = file_get_contents($configFilePath);
        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error decoding JSON: " . json_last_error_msg());
        }

        if (isset($data['mode'])) $config->setMode($data['mode']);
        if (isset($data['fallback'])) $config->setFallback($data['fallback']);
        if (isset($data['environment'])) $config->setEnvironment($data['environment']);
        if (isset($data['rootSuite'])) $config->setRootSuite($data['rootSuite']);
        if (isset($data['debug'])) $config->setDebug($data['debug']);

        if (isset($data['testops']['project'])) $config->testops->setProject($data['testops']['project']);
        if (isset($data['testops']['defect'])) $config->testops->setDefect($data['testops']['defect']);

        if (isset($data['testops']['api']['token'])) $config->testops->api->setToken($data['testops']['api']['token']);
        if (isset($data['testops']['api']['host'])) $config->testops->api->setHost($data['testops']['api']['host']);

        if (isset($data['testops']['run']['id'])) $config->testops->run->setId($data['testops']['run']['id']);
        if (isset($data['testops']['run']['title'])) $config->testops->run->setTitle($data['testops']['run']['title']);
        if (isset($data['testops']['run']['description'])) $config->testops->run->setDescription($data['testops']['run']['description']);
        if (isset($data['testops']['run']['complete'])) $config->testops->run->setComplete($data['testops']['run']['complete']);
        if (isset($data['testops']['run']['tags'])) $config->testops->run->setTags($data['testops']['run']['tags']);

        if (isset($data['testops']['plan']['id'])) $config->testops->plan->setId($data['testops']['plan']['id']);

        if (isset($data['testops']['batch']['size'])) $config->testops->batch->setSize($data['testops']['batch']['size']);

        if (isset($data['testops']['configurations']['values'])) {
            $config->testops->configurations->setValues($data['testops']['configurations']['values']);
        }
        if (isset($data['testops']['configurations']['createIfNotExists'])) {
            $config->testops->configurations->setCreateIfNotExists($data['testops']['configurations']['createIfNotExists']);
        }

        if (isset($data['report']['driver'])) $config->report->setDriver($data['report']['driver']);

        if (isset($data['report']['connection']['path'])) $config->report->connection->setPath($data['report']['connection']['path']);
        if (isset($data['report']['connection']['format'])) $config->report->connection->setFormat($data['report']['connection']['format']);

        return $config;
    }

    private function overrideWithEnvVariables(): void
    {
        $env_vars = getenv();
        foreach ($env_vars as $key => $value) {
            switch (strtolower($key)) {
                case "qase_mode":
                    $this->config->setMode($value);
                    break;
                case "qase_fallback":
                    $this->config->setFallback($value);
                    break;
                case "qase_environment":
                    $this->config->setEnvironment($value);
                    break;
                case "qase_root_suite":
                    $this->config->setRootSuite($value);
                    break;
                case "qase_debug":
                    $this->config->setDebug($value);
                    break;
                case "qase_testops_project":
                    $this->config->testops->setProject($value);
                    break;
                case "qase_testops_defect":
                    $this->config->testops->setDefect($value);
                    break;
                case "qase_testops_api_token":
                    $this->config->testops->api->setToken($value);
                    break;
                case "qase_testops_api_host":
                    $this->config->testops->api->setHost($value);
                    break;
                case "qase_testops_run_id":
                    $this->config->testops->run->setId($value);
                    break;
                case "qase_testops_run_title":
                    $this->config->testops->run->setTitle($value);
                    break;
                case "qase_testops_run_description":
                    $this->config->testops->run->setDescription($value);
                    break;
                case "qase_testops_run_complete":
                    $this->config->testops->run->setComplete($value);
                    break;
                case "qase_testops_run_tags":
                    $this->config->testops->run->setTags(array_map('trim', explode(',', $value)));
                    break;
                case "qase_testops_plan_id":
                    $this->config->testops->plan->setId($value);
                    break;
                case "qase_testops_batch_size":
                    $this->config->testops->batch->setSize($value);
                    break;
                case "qase_testops_configurations_values":
                    $this->parseConfigurationValues($value);
                    break;
                case "qase_testops_configurations_create_if_not_exists":
                    $this->config->testops->configurations->setCreateIfNotExists($value);
                    break;
                case "qase_report_driver":
                    $this->config->report->setDriver($value);
                    break;
                case "qase_report_connection_path":
                    $this->config->report->connection->setPath($value);
                    break;
                case "qase_report_connection_format":
                    $this->config->report->connection->setFormat($value);
                    break;
            }
        }
    }

    /**
     * Parse configuration values from comma-separated key=value pairs
     * 
     * @param string $value Comma-separated key=value pairs
     */
    private function parseConfigurationValues(string $value): void
    {
        $pairs = array_map('trim', explode(',', $value));
        $configurations = [];

        foreach ($pairs as $pair) {
            if (strpos($pair, '=') === false) {
                continue;
            }

            list($name, $configValue) = explode('=', $pair, 2);
            $name = trim($name);
            $configValue = trim($configValue);

            if (!empty($name) && !empty($configValue)) {
                $configurations[] = [
                    'name' => $name,
                    'value' => $configValue
                ];
            }
        }

        $this->config->testops->configurations->setValues($configurations);
    }
}
