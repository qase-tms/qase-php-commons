<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Utils;

abstract class HeaderManager
{
    protected const UNDEFINED_HEADER = 'undefined';

    protected array $composerPackages = [];

    protected function init(): void
    {
        $composerFilepath = __DIR__ . '/../../../../composer.lock';
        if (!file_exists($composerFilepath)) {
            return;
        }

        $composerLock = \json_decode(file_get_contents($composerFilepath), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return;
        }

        $packages = array_column($composerLock['packages'] ?? [], 'version', 'name');
        $packagesDev = array_column($composerLock['packages-dev'] ?? [], 'version', 'name');
        $this->composerPackages = array_merge($packages, $packagesDev);
    }

    abstract public function getClientHeaders(): array;
}
