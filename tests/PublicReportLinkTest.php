<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Config\ConfigLoader;

class PublicReportLinkTest extends TestCase
{
    public function testShowPublicReportLinkFromEnvTrue(): void
    {
        // Set environment variable
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK=true');

        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        $this->assertTrue($config->testops->isShowPublicReportLink());

        // Clean up
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK');
    }

    public function testShowPublicReportLinkFromEnvFalse(): void
    {
        // Set environment variable
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK=false');

        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        $this->assertFalse($config->testops->isShowPublicReportLink());

        // Clean up
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK');
    }

    public function testShowPublicReportLinkFromEnv1(): void
    {
        // Set environment variable with numeric value
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK=1');

        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        $this->assertTrue($config->testops->isShowPublicReportLink());

        // Clean up
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK');
    }

    public function testShowPublicReportLinkFromEnv0(): void
    {
        // Set environment variable with numeric value
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK=0');

        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        $this->assertFalse($config->testops->isShowPublicReportLink());

        // Clean up
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK');
    }

    public function testShowPublicReportLinkDefaultValue(): void
    {
        // Don't set environment variable
        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        // Should be false by default
        $this->assertFalse($config->testops->isShowPublicReportLink());
    }

    public function testShowPublicReportLinkFromJsonFile(): void
    {
        // Create a temporary config file
        $configFilePath = getcwd() . '/qase.config.json';
        $configData = [
            'testops' => [
                'showPublicReportLink' => true,
                'project' => 'TEST',
                'api' => [
                    'token' => 'test_token'
                ]
            ]
        ];

        file_put_contents($configFilePath, json_encode($configData));

        // Load config
        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        $this->assertTrue($config->testops->isShowPublicReportLink());

        // Clean up
        unlink($configFilePath);
    }

    public function testShowPublicReportLinkFromJsonFileFalse(): void
    {
        // Create a temporary config file
        $configFilePath = getcwd() . '/qase.config.json';
        $configData = [
            'testops' => [
                'showPublicReportLink' => false,
                'project' => 'TEST',
                'api' => [
                    'token' => 'test_token'
                ]
            ]
        ];

        file_put_contents($configFilePath, json_encode($configData));

        // Load config
        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        $this->assertFalse($config->testops->isShowPublicReportLink());

        // Clean up
        unlink($configFilePath);
    }

    public function testShowPublicReportLinkEnvOverridesFile(): void
    {
        // Create a temporary config file with false
        $configFilePath = getcwd() . '/qase.config.json';
        $configData = [
            'testops' => [
                'showPublicReportLink' => false,
                'project' => 'TEST',
                'api' => [
                    'token' => 'test_token'
                ]
            ]
        ];

        file_put_contents($configFilePath, json_encode($configData));

        // Set environment variable to true
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK=true');

        // Load config
        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        // Environment variable should override file config
        $this->assertTrue($config->testops->isShowPublicReportLink());

        // Clean up
        unlink($configFilePath);
        putenv('QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK');
    }

    public function testSetShowPublicReportLink(): void
    {
        $configLoader = new ConfigLoader();
        $config = $configLoader->getConfig();

        // Test setter
        $config->testops->setShowPublicReportLink(true);
        $this->assertTrue($config->testops->isShowPublicReportLink());

        $config->testops->setShowPublicReportLink(false);
        $this->assertFalse($config->testops->isShowPublicReportLink());
    }
}

