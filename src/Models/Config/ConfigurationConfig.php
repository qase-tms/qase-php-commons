<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models\Config;

class ConfigurationConfig
{
    /**
     * @var array<array{name: string, value: string}> Configuration values to associate with the test run
     */
    public array $values = [];

    /**
     * @var bool Whether to create configuration groups and values if they don't exist
     */
    public bool $createIfNotExists = false;

    public function __construct()
    {
    }

    /**
     * Get configuration values
     * 
     * @return array<array{name: string, value: string}>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Set configuration values
     * 
     * @param array<array{name: string, value: string}> $values
     */
    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    /**
     * Add a configuration value
     * 
     * @param string $name
     * @param string $value
     */
    public function addValue(string $name, string $value): void
    {
        $this->values[] = [
            'name' => $name,
            'value' => $value
        ];
    }

    /**
     * Get createIfNotExists flag
     */
    public function isCreateIfNotExists(): bool
    {
        return $this->createIfNotExists;
    }

    /**
     * Set createIfNotExists flag
     */
    public function setCreateIfNotExists(bool $createIfNotExists): void
    {
        $this->createIfNotExists = $createIfNotExists;
    }
} 
