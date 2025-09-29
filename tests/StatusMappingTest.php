<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Loggers\Logger;
use Qase\PhpCommons\Utils\StatusMapping;

class StatusMappingTest extends TestCase
{
    private Logger $logger;
    private StatusMapping $statusMapping;

    protected function setUp(): void
    {
        $this->logger = new Logger(false);
        $this->statusMapping = new StatusMapping($this->logger);
    }

    public function testValidStatuses(): void
    {
        $validStatuses = StatusMapping::getValidStatuses();
        $expectedStatuses = ['passed', 'failed', 'skipped', 'blocked', 'invalid'];
        
        $this->assertEquals($expectedStatuses, $validStatuses);
    }

    public function testIsValidStatus(): void
    {
        $this->assertTrue(StatusMapping::isValidStatus('passed'));
        $this->assertTrue(StatusMapping::isValidStatus('failed'));
        $this->assertTrue(StatusMapping::isValidStatus('skipped'));
        $this->assertTrue(StatusMapping::isValidStatus('blocked'));
        $this->assertTrue(StatusMapping::isValidStatus('invalid'));
        
        $this->assertFalse(StatusMapping::isValidStatus('unknown'));
        $this->assertFalse(StatusMapping::isValidStatus(''));
        $this->assertFalse(StatusMapping::isValidStatus('PASSED')); // case sensitive
    }

    public function testSetValidMapping(): void
    {
        $mapping = [
            'invalid' => 'failed',
            'skipped' => 'passed'
        ];
        
        $this->statusMapping->setMapping($mapping);
        
        $this->assertEquals($mapping, $this->statusMapping->getMapping());
        $this->assertFalse($this->statusMapping->isEmpty());
    }

    public function testSetInvalidMapping(): void
    {
        $mapping = [
            'invalid' => 'failed',
            'unknown' => 'passed', // invalid source
            'skipped' => 'unknown', // invalid target
            'passed' => 'passed' // redundant mapping
        ];
        
        $this->statusMapping->setMapping($mapping);
        
        // Only valid mappings should be kept
        $expectedMapping = ['invalid' => 'failed'];
        $this->assertEquals($expectedMapping, $this->statusMapping->getMapping());
    }

    public function testMapStatus(): void
    {
        $mapping = [
            'invalid' => 'failed',
            'skipped' => 'passed'
        ];
        
        $this->statusMapping->setMapping($mapping);
        
        $this->assertEquals('failed', $this->statusMapping->mapStatus('invalid'));
        $this->assertEquals('passed', $this->statusMapping->mapStatus('skipped'));
        $this->assertEquals('blocked', $this->statusMapping->mapStatus('blocked')); // no mapping
        $this->assertEquals('unknown', $this->statusMapping->mapStatus('unknown')); // invalid status
    }

    public function testParseFromEnvValid(): void
    {
        $envValue = 'invalid=failed,skipped=passed';
        $this->statusMapping->parseFromEnv($envValue);
        
        $expectedMapping = [
            'invalid' => 'failed',
            'skipped' => 'passed'
        ];
        
        $this->assertEquals($expectedMapping, $this->statusMapping->getMapping());
    }

    public function testParseFromEnvInvalid(): void
    {
        $envValue = 'invalid=failed,invalid-pair,skipped=passed';
        $this->statusMapping->parseFromEnv($envValue);
        
        $expectedMapping = [
            'invalid' => 'failed',
            'skipped' => 'passed'
        ];
        
        $this->assertEquals($expectedMapping, $this->statusMapping->getMapping());
    }

    public function testParseFromEnvEmpty(): void
    {
        $this->statusMapping->parseFromEnv('');
        $this->assertTrue($this->statusMapping->isEmpty());
        
        $this->statusMapping->parseFromEnv('   ');
        $this->assertTrue($this->statusMapping->isEmpty());
    }

    public function testParseFromEnvWithSpaces(): void
    {
        $envValue = ' invalid = failed , skipped = passed ';
        $this->statusMapping->parseFromEnv($envValue);
        
        $expectedMapping = [
            'invalid' => 'failed',
            'skipped' => 'passed'
        ];
        
        $this->assertEquals($expectedMapping, $this->statusMapping->getMapping());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->statusMapping->isEmpty());
        
        $this->statusMapping->setMapping(['invalid' => 'failed']);
        $this->assertFalse($this->statusMapping->isEmpty());
        
        $this->statusMapping->setMapping([]);
        $this->assertTrue($this->statusMapping->isEmpty());
    }

    public function testNonStringMappingValues(): void
    {
        $mapping = [
            'invalid' => 'failed',
            123 => 'passed', // non-string key
            'skipped' => 456 // non-string value
        ];
        
        $this->statusMapping->setMapping($mapping);
        
        // Only valid string mappings should be kept
        $expectedMapping = ['invalid' => 'failed'];
        $this->assertEquals($expectedMapping, $this->statusMapping->getMapping());
    }

    public function testCaseSensitiveMapping(): void
    {
        $mapping = [
            'INVALID' => 'failed', // uppercase source
            'skipped' => 'PASSED' // uppercase target
        ];
        
        $this->statusMapping->setMapping($mapping);
        
        // Case-sensitive validation should reject these
        $this->assertTrue($this->statusMapping->isEmpty());
    }
}
