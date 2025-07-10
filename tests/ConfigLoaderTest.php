<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Config\ConfigLoader;
use Qase\PhpCommons\Loggers\Logger;

class ConfigLoaderTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger();
    }

    public function testConfigurationValuesFromEnv(): void
    {
        // Set environment variables
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES=browser=chrome,version=latest,environment=staging');
        putenv('QASE_TESTOPS_CONFIGURATIONS_CREATE_IF_NOT_EXISTS=true');

        $configLoader = new ConfigLoader($this->logger);
        $config = $configLoader->getConfig();

        $this->assertTrue($config->testops->configurations->isCreateIfNotExists());
        
        $values = $config->testops->configurations->getValues();
        $this->assertCount(3, $values);
        
        $this->assertEquals('browser', $values[0]['name']);
        $this->assertEquals('chrome', $values[0]['value']);
        
        $this->assertEquals('version', $values[1]['name']);
        $this->assertEquals('latest', $values[1]['value']);
        
        $this->assertEquals('environment', $values[2]['name']);
        $this->assertEquals('staging', $values[2]['value']);

        // Clean up
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES');
        putenv('QASE_TESTOPS_CONFIGURATIONS_CREATE_IF_NOT_EXISTS');
    }

    public function testConfigurationValuesFromEnvWithSpaces(): void
    {
        // Set environment variables with spaces
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES=browser=chrome, version=latest , environment=staging');

        $configLoader = new ConfigLoader($this->logger);
        $config = $configLoader->getConfig();

        $values = $config->testops->configurations->getValues();
        $this->assertCount(3, $values);
        
        $this->assertEquals('browser', $values[0]['name']);
        $this->assertEquals('chrome', $values[0]['value']);
        
        $this->assertEquals('version', $values[1]['name']);
        $this->assertEquals('latest', $values[1]['value']);
        
        $this->assertEquals('environment', $values[2]['name']);
        $this->assertEquals('staging', $values[2]['value']);

        // Clean up
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES');
    }

    public function testConfigurationValuesFromEnvInvalidFormat(): void
    {
        // Set environment variables with invalid format
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES=invalid_format,another_invalid');

        $configLoader = new ConfigLoader($this->logger);
        $config = $configLoader->getConfig();

        $values = $config->testops->configurations->getValues();
        $this->assertEmpty($values);

        // Clean up
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES');
    }

    public function testConfigurationValuesFromEnvMixedFormat(): void
    {
        // Set environment variables with mixed valid and invalid format
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES=invalid,browser=chrome,another_invalid,version=latest');

        $configLoader = new ConfigLoader($this->logger);
        $config = $configLoader->getConfig();

        $values = $config->testops->configurations->getValues();
        $this->assertCount(2, $values);
        
        $this->assertEquals('browser', $values[0]['name']);
        $this->assertEquals('chrome', $values[0]['value']);
        
        $this->assertEquals('version', $values[1]['name']);
        $this->assertEquals('latest', $values[1]['value']);

        // Clean up
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES');
    }
} 
