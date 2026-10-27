<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Reporters\FileReporter;
use ReflectionClass;

class FileReporterJsonTest extends TestCase
{
    public function testMalformedUtf8IsSubstitutedInsteadOfDroppingTheReport(): void
    {
        $reflection = new ReflectionClass(FileReporter::class);
        $reporter = $reflection->newInstanceWithoutConstructor();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        $property->setValue($reporter, $logger);

        $method = $reflection->getMethod('convertToJson');
        $method->setAccessible(true);
        $json = $method->invoke($reporter, ['title' => "\x80\x94 OTP emailed — ok"]);

        $this->assertSame("\u{FFFD}\u{FFFD} OTP emailed — ok", json_decode($json, true)['title']);
    }
}
