<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Models\Config\ConfigurationConfig;
use Qase\PhpCommons\Models\ConfigurationGroup;
use Qase\PhpCommons\Models\ConfigurationItem;

class ConfigurationTest extends TestCase
{
    public function testConfigurationConfigCreation(): void
    {
        $config = new ConfigurationConfig();
        
        $this->assertInstanceOf(ConfigurationConfig::class, $config);
        $this->assertEmpty($config->getValues());
        $this->assertFalse($config->isCreateIfNotExists());
    }

    public function testConfigurationConfigValues(): void
    {
        $config = new ConfigurationConfig();
        
        $values = [
            [
                'name' => 'browser',
                'value' => 'chrome'
            ],
            [
                'name' => 'version',
                'value' => 'latest'
            ],
            [
                'name' => 'environment',
                'value' => 'staging'
            ]
        ];
        
        $config->setValues($values);
        $this->assertEquals($values, $config->getValues());
        
        $config->addValue('platform', 'linux');
        $this->assertCount(4, $config->getValues());
        $this->assertEquals('platform', $config->getValues()[3]['name']);
        $this->assertEquals('linux', $config->getValues()[3]['value']);
    }

    public function testConfigurationConfigCreateIfNotExists(): void
    {
        $config = new ConfigurationConfig();
        
        $config->setCreateIfNotExists(true);
        $this->assertTrue($config->isCreateIfNotExists());
        
        $config->setCreateIfNotExists(false);
        $this->assertFalse($config->isCreateIfNotExists());
    }

    public function testConfigurationGroupCreation(): void
    {
        $group = new ConfigurationGroup(1, 'Test Group');
        
        $this->assertInstanceOf(ConfigurationGroup::class, $group);
        $this->assertEquals(1, $group->getId());
        $this->assertEquals('Test Group', $group->getTitle());
    }

    public function testConfigurationGroupSetters(): void
    {
        $group = new ConfigurationGroup();
        
        $group->setId(2);
        $group->setTitle('New Title');
        
        $this->assertEquals(2, $group->getId());
        $this->assertEquals('New Title', $group->getTitle());
    }

    public function testConfigurationItemCreation(): void
    {
        $item = new ConfigurationItem(1, 'Test Item');
        
        $this->assertInstanceOf(ConfigurationItem::class, $item);
        $this->assertEquals(1, $item->getId());
        $this->assertEquals('Test Item', $item->getTitle());
    }

    public function testConfigurationItemSetters(): void
    {
        $item = new ConfigurationItem();
        
        $item->setId(2);
        $item->setTitle('New Item');
        
        $this->assertEquals(2, $item->getId());
        $this->assertEquals('New Item', $item->getTitle());
    }
} 
