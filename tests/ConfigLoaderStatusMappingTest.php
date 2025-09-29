<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Config\ConfigLoader;
use Qase\PhpCommons\Loggers\Logger;

class ConfigLoaderStatusMappingTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger(false);
    }

    public function testLoadStatusMappingFromJson(): void
    {
        // Create temporary config file
        $configData = [
            'mode' => 'off',
            'statusMapping' => [
                'invalid' => 'failed',
                'skipped' => 'passed'
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'qase_config_');
        file_put_contents($tempFile, json_encode($configData));
        
        // Change working directory to temp directory
        $originalCwd = getcwd();
        chdir(dirname($tempFile));
        
        // Rename temp file to qase.config.json
        $configPath = dirname($tempFile) . '/qase.config.json';
        rename($tempFile, $configPath);
        
        try {
            $configLoader = new ConfigLoader($this->logger);
            $config = $configLoader->getConfig();
            
            $expectedMapping = [
                'invalid' => 'failed',
                'skipped' => 'passed'
            ];
            
            $this->assertEquals($expectedMapping, $config->getStatusMapping());
        } finally {
            chdir($originalCwd);
            if (file_exists($configPath)) {
                unlink($configPath);
            }
        }
    }

    public function testLoadStatusMappingFromEnv(): void
    {
        // Set environment variable
        putenv('QASE_STATUS_MAPPING=invalid=failed,skipped=passed');
        
        try {
            $configLoader = new ConfigLoader($this->logger);
            $config = $configLoader->getConfig();
            
            $expectedMapping = [
                'invalid' => 'failed',
                'skipped' => 'passed'
            ];
            
            $this->assertEquals($expectedMapping, $config->getStatusMapping());
        } finally {
            putenv('QASE_STATUS_MAPPING');
        }
    }

    public function testEnvOverridesJson(): void
    {
        // Create temporary config file
        $configData = [
            'mode' => 'off',
            'statusMapping' => [
                'invalid' => 'blocked'
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'qase_config_');
        file_put_contents($tempFile, json_encode($configData));
        
        // Change working directory to temp directory
        $originalCwd = getcwd();
        chdir(dirname($tempFile));
        
        // Set environment variable
        putenv('QASE_STATUS_MAPPING=invalid=failed,skipped=passed');
        
        try {
            $configLoader = new ConfigLoader($this->logger);
            $config = $configLoader->getConfig();
            
            // Environment should override JSON
            $expectedMapping = [
                'invalid' => 'failed',
                'skipped' => 'passed'
            ];
            
            $this->assertEquals($expectedMapping, $config->getStatusMapping());
        } finally {
            chdir($originalCwd);
            unlink($tempFile);
            putenv('QASE_STATUS_MAPPING');
        }
    }

    public function testEmptyStatusMapping(): void
    {
        $configLoader = new ConfigLoader($this->logger);
        $config = $configLoader->getConfig();
        
        $this->assertEmpty($config->getStatusMapping());
    }

    public function testInvalidStatusMappingInJson(): void
    {
        // Create temporary config file with invalid mapping
        $configData = [
            'mode' => 'off',
            'statusMapping' => [
                'invalid' => 'failed',
                'unknown' => 'passed', // invalid source
                'skipped' => 'unknown' // invalid target
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'qase_config_');
        file_put_contents($tempFile, json_encode($configData));
        
        // Change working directory to temp directory
        $originalCwd = getcwd();
        chdir(dirname($tempFile));
        
        // Rename temp file to qase.config.json
        $configPath = dirname($tempFile) . '/qase.config.json';
        rename($tempFile, $configPath);
        
        try {
            $configLoader = new ConfigLoader($this->logger);
            $config = $configLoader->getConfig();
            
            // Only valid mappings should be kept
            $expectedMapping = ['invalid' => 'failed'];
            $this->assertEquals($expectedMapping, $config->getStatusMapping());
        } finally {
            chdir($originalCwd);
            if (file_exists($configPath)) {
                unlink($configPath);
            }
        }
    }

    public function testInvalidStatusMappingInEnv(): void
    {
        // Set environment variable with invalid mapping
        putenv('QASE_STATUS_MAPPING=invalid=failed,unknown=passed,skipped=unknown');
        
        try {
            $configLoader = new ConfigLoader($this->logger);
            $config = $configLoader->getConfig();
            
            // Only valid mappings should be kept
            $expectedMapping = ['invalid' => 'failed'];
            $this->assertEquals($expectedMapping, $config->getStatusMapping());
        } finally {
            putenv('QASE_STATUS_MAPPING');
        }
    }
}
