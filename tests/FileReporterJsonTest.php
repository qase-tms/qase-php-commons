<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\ReportConfig;
use Qase\PhpCommons\Reporters\FileReporter;
use ReflectionClass;

class FileReporterJsonTest extends TestCase
{
    public function testMalformedUtf8IsSubstitutedInsteadOfDroppingTheReport(): void
    {
        $reporter = (new ReflectionClass(FileReporter::class))->newInstanceWithoutConstructor();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        (new ReflectionClass(FileReporter::class))->getProperty('logger')->setValue($reporter, $logger);

        $json = (new ReflectionClass(FileReporter::class))
            ->getMethod('convertToJson')
            ->invoke($reporter, ['title' => "\x80\x94 OTP emailed — ok"]);

        $this->assertSame("\u{FFFD}\u{FFFD} OTP emailed — ok", json_decode($json, true)['title']);
    }
}
