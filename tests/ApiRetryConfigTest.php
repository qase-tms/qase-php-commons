<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Config\ConfigLoader;
use Qase\PhpCommons\Models\Config\ApiConfig;

class ApiRetryConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $api = new ApiConfig();

        $this->assertSame(30, $api->getTimeout());
        $this->assertSame(3, $api->getRetries());
        $this->assertSame(1.0, $api->getRetryBackoff());
    }

    public function testValuesFromJsonFile(): void
    {
        $configFilePath = getcwd() . '/qase.config.json';
        file_put_contents($configFilePath, json_encode([
            'testops' => [
                'project' => 'TEST',
                'api' => [
                    'token' => 'test_token',
                    'timeout' => 45,
                    'retries' => 5,
                    'retryBackoff' => 2.5,
                ],
            ],
        ]));

        try {
            $config = (new ConfigLoader())->getConfig();

            $this->assertSame(45, $config->testops->api->getTimeout());
            $this->assertSame(5, $config->testops->api->getRetries());
            $this->assertSame(2.5, $config->testops->api->getRetryBackoff());
        } finally {
            unlink($configFilePath);
        }
    }

    public function testValuesFromEnvVariables(): void
    {
        putenv('QASE_TESTOPS_API_TIMEOUT=12');
        putenv('QASE_TESTOPS_API_RETRIES=7');
        putenv('QASE_TESTOPS_API_RETRY_BACKOFF=0.25');

        try {
            $config = (new ConfigLoader())->getConfig();

            $this->assertSame(12, $config->testops->api->getTimeout());
            $this->assertSame(7, $config->testops->api->getRetries());
            $this->assertSame(0.25, $config->testops->api->getRetryBackoff());
        } finally {
            putenv('QASE_TESTOPS_API_TIMEOUT');
            putenv('QASE_TESTOPS_API_RETRIES');
            putenv('QASE_TESTOPS_API_RETRY_BACKOFF');
        }
    }

    public function testNonPositiveTimeoutFallsBackToTheDefault(): void
    {
        $api = new ApiConfig();

        $api->setTimeout(0);
        $this->assertSame(30, $api->getTimeout(), 'A zero timeout would let a stalled connection hang forever');

        $api->setTimeout(-5);
        $this->assertSame(30, $api->getTimeout());
    }

    public function testJsonSerializeExposesRetrySettings(): void
    {
        $api = new ApiConfig();
        $api->setTimeout(15);
        $api->setRetries(2);
        $api->setRetryBackoff(0.5);

        $serialized = $api->jsonSerialize();

        $this->assertSame(15, $serialized['timeout']);
        $this->assertSame(2, $serialized['retries']);
        $this->assertSame(0.5, $serialized['retryBackoff']);
    }
}
