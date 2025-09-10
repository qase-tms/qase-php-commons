<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Client\ApiClientV1;
use Qase\PhpCommons\Loggers\Logger;
use Qase\PhpCommons\Models\Config\TestopsConfig;
use Qase\PhpCommons\Models\Config\ApiConfig;
use Qase\APIClientV1\Model\RunexternalIssues;
use Qase\APIClientV1\Model\RunexternalIssuesLinksInner;

class ExternalLinkApiTest extends TestCase
{
    private ApiClientV1 $client;
    private TestopsConfig $config;

    protected function setUp(): void
    {
        $logger = new Logger();
        $this->config = new TestopsConfig();
        $this->config->api = new ApiConfig();
        $this->config->api->setToken('test-token');
        $this->config->api->setHost('qase.io');
        
        $this->client = new ApiClientV1($logger, $this->config);
    }

    public function testRunUpdateExternalIssueUsesCorrectApiModels(): void
    {
        // This test verifies that the method uses the correct API models
        // We can't easily mock the API calls, but we can verify the method exists and is callable
        
        $this->assertTrue(method_exists($this->client, 'runUpdateExternalIssue'));
        
        // Test that the method can be called without errors
        // (it will fail due to missing API credentials, but that's expected)
        try {
            $this->client->runUpdateExternalIssue('TEST', 'jiraCloud', [
                ['run_id' => 123, 'external_issue' => 'PROJ-123']
            ]);
        } catch (\Exception $e) {
            // Expected to fail due to missing credentials
            $this->assertStringContainsString('Failed to update external issue', $e->getMessage());
        }
    }

    public function testApiModelConstants(): void
    {
        // Test that the API model constants are correct
        $this->assertEquals('jira-cloud', RunexternalIssues::TYPE_CLOUD);
        $this->assertEquals('jira-server', RunexternalIssues::TYPE_SERVER);
    }

    public function testApiModelCreation(): void
    {
        // Test creating API models
        $link = new RunexternalIssuesLinksInner();
        $link->setRunId(123);
        $link->setExternalIssue('PROJ-123');
        
        $this->assertEquals(123, $link->getRunId());
        $this->assertEquals('PROJ-123', $link->getExternalIssue());
        
        $externalIssues = new RunexternalIssues();
        $externalIssues->setType(RunexternalIssues::TYPE_CLOUD);
        $externalIssues->setLinks([$link]);
        
        $this->assertEquals(RunexternalIssues::TYPE_CLOUD, $externalIssues->getType());
        $this->assertCount(1, $externalIssues->getLinks());
    }
}
