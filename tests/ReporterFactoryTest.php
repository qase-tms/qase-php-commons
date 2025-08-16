<?php
declare(strict_types=1);
namespace Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;
use Qase\PhpCommons\Reporters\CoreReporter;
use Qase\PhpCommons\Reporters\ReporterFactory;
use Qase\PhpCommons\Utils\HostInfo;

class ReporterFactoryTest extends TestCase
{
    private MockObject&QaseConfig $config;
    private MockObject&HostInfo $hostInfo;
    private MockObject&StateInterface $state;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(QaseConfig::class);
        $this->hostInfo = $this->createMock(HostInfo::class);
        $this->state = $this->createMock(StateInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testConstructorSetsProperties(): void
    {
        $factory = $this->createSubject();

        $this->assertInstanceOf(ReporterFactory::class, $factory);
    }

    public function testCreateReporterWithOffMode(): void
    {
        $this->config->expects($this->once())
            ->method('getDebug')
            ->willReturn(false);

        $this->hostInfo->expects($this->once())
            ->method('getHostInfo')
            ->willReturn([]);

        $this->logger->expects($this->once())
            ->method('debug');

        $this->config->expects($this->once())
            ->method('getMode')
            ->willReturn('off');

        $this->config->expects($this->once())
            ->method('getFallback')
            ->willReturn('off');

        $this->config->expects($this->once())
            ->method('getRootSuite')
            ->willReturn(null);

        $factory = $this->createSubject();

        $reporter = $factory->createReporter();

        $this->assertInstanceOf(CoreReporter::class, $reporter);
    }

    public function testCreateReporterWithDifferentFallbackMode(): void
    {
        $this->config->expects($this->once())
            ->method('getDebug')
            ->willReturn(false);

        $this->hostInfo->expects($this->once())
            ->method('getHostInfo')
            ->willReturn([]);

        $this->logger->expects($this->once())
            ->method('debug');

        $this->config->expects($this->once())
            ->method('getMode')
            ->willReturn('off');

        $this->config->expects($this->once())
            ->method('getFallback')
            ->willReturn('off');

        $this->config->expects($this->once())
            ->method('getRootSuite')
            ->willReturn('Custom Root Suite');

        $factory = $this->createSubject();

        $reporter = $factory->createReporter('jest', 'custom-reporter');

        $this->assertInstanceOf(CoreReporter::class, $reporter);
    }

    public function testWithDebugEnabledItWIllUseANewLoggerInstance()
    {
        $this->config->expects($this->once())
            ->method('getDebug')
            ->willReturn(true);

        $this->hostInfo->expects($this->once())
            ->method('getHostInfo')
            ->willReturn([]);

        $this->logger->expects($this->never())
            ->method('debug');

        $this->config->expects($this->once())
            ->method('getMode')
            ->willReturn('off');

        $this->config->expects($this->once())
            ->method('getFallback')
            ->willReturn('off');

        $this->config->expects($this->once())
            ->method('getRootSuite')
            ->willReturn('Custom Root Suite');

        $factory = $this->createSubject();

        $reporter = $factory->createReporter('jest', 'custom-reporter');

        $this->assertInstanceOf(CoreReporter::class, $reporter);
    }

    public function testCreateStaticMethod(): void
    {
        $reporter = ReporterFactory::create('phpunit', 'test-reporter');

        $this->assertInstanceOf(CoreReporter::class, $reporter);
    }

    public function testCreateStaticMethodWithDefaults(): void
    {
        $reporter = ReporterFactory::create();

        $this->assertInstanceOf(CoreReporter::class, $reporter);
    }

    private function createSubject(): ReporterFactory
    {
        return new ReporterFactory(
            $this->config,
            $this->hostInfo,
            $this->state,
            $this->logger
        );
    }
}
