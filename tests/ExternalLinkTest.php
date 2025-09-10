<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Models\Config\ExternalLinkType;
use Qase\PhpCommons\Models\Config\TestOpsExternalLinkType;
use Qase\PhpCommons\Models\Config\RunConfig;

class ExternalLinkTest extends TestCase
{
    public function testExternalLinkTypeConstants(): void
    {
        $this->assertEquals('jiraCloud', ExternalLinkType::JIRA_CLOUD);
        $this->assertEquals('jiraServer', ExternalLinkType::JIRA_SERVER);
    }

    public function testExternalLinkTypeValidation(): void
    {
        $this->assertTrue(ExternalLinkType::isValid('jiraCloud'));
        $this->assertTrue(ExternalLinkType::isValid('jiraServer'));
        $this->assertFalse(ExternalLinkType::isValid('invalidType'));
        $this->assertFalse(ExternalLinkType::isValid(''));
    }

    public function testExternalLinkTypeGetAll(): void
    {
        $allTypes = ExternalLinkType::getAll();
        $this->assertContains('jiraCloud', $allTypes);
        $this->assertContains('jiraServer', $allTypes);
        $this->assertCount(2, $allTypes);
    }

    public function testTestOpsExternalLinkTypeCreation(): void
    {
        $externalLink = new TestOpsExternalLinkType('jiraCloud', 'https://example.com/issue/123');
        
        $this->assertEquals('jiraCloud', $externalLink->getType());
        $this->assertEquals('https://example.com/issue/123', $externalLink->getLink());
    }

    public function testTestOpsExternalLinkTypeInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid external link type: invalidType');
        
        new TestOpsExternalLinkType('invalidType', 'https://example.com/issue/123');
    }

    public function testTestOpsExternalLinkTypeSetters(): void
    {
        $externalLink = new TestOpsExternalLinkType('jiraCloud', 'https://example.com/issue/123');
        
        $externalLink->setType('jiraServer');
        $externalLink->setLink('https://jira.example.com/browse/PROJ-456');
        
        $this->assertEquals('jiraServer', $externalLink->getType());
        $this->assertEquals('https://jira.example.com/browse/PROJ-456', $externalLink->getLink());
    }

    public function testTestOpsExternalLinkTypeInvalidSetter(): void
    {
        $externalLink = new TestOpsExternalLinkType('jiraCloud', 'https://example.com/issue/123');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid external link type: invalidType');
        
        $externalLink->setType('invalidType');
    }

    public function testRunConfigExternalLink(): void
    {
        $runConfig = new RunConfig();
        
        $this->assertNull($runConfig->getExternalLink());
        
        $externalLink = new TestOpsExternalLinkType('jiraCloud', 'https://example.com/issue/123');
        $runConfig->setExternalLink($externalLink);
        
        $this->assertSame($externalLink, $runConfig->getExternalLink());
        
        $runConfig->setExternalLink(null);
        $this->assertNull($runConfig->getExternalLink());
    }
}
