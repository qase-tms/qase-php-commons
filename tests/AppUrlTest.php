<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Client\ApiClientV1;
use Qase\PhpCommons\Client\ApiClientV2;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Config\TestopsConfig;
use ReflectionProperty;

class AppUrlTest extends TestCase
{
    /**
     * @dataProvider hostProvider
     */
    public function testAppUrlMatchesTheClusterOfTheApiHost(string $host, string $expectedAppUrl): void
    {
        $this->assertSame($expectedAppUrl, $this->appUrl(new ApiClientV1($this->logger(), $this->config($host))));
    }

    /**
     * @dataProvider hostProvider
     */
    public function testApiClientV2SharesTheAppUrlResolution(string $host, string $expectedAppUrl): void
    {
        $this->assertSame($expectedAppUrl, $this->appUrl(new ApiClientV2($this->logger(), $this->config($host))));
    }

    public static function hostProvider(): array
    {
        return [
            'default' => ['qase.io', 'https://app.qase.io'],
            'eu cluster' => ['eu.qase.io', 'https://app-eu.qase.io'],
            'enterprise' => ['example.qase.io', 'https://example.qase.io'],
        ];
    }

    private function appUrl(ApiClientV1 $client): string
    {
        $property = new ReflectionProperty(ApiClientV1::class, 'appUrl');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property->getValue($client);
    }

    private function config(string $host): TestopsConfig
    {
        $config = new TestopsConfig();
        $config->api->setToken('token');
        $config->api->setHost($host);

        return $config;
    }

    private function logger(): LoggerInterface
    {
        return $this->createMock(LoggerInterface::class);
    }
}
