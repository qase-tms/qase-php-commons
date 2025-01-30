<?php

namespace Qase\PhpCommons\Models\Config;

class Driver
{
    private const LOCAL = 'local';

    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function local(): self
    {
        return new self(self::LOCAL);
    }

    public static function fromValue(string $value): ?self
    {
        $validValues = [self::LOCAL];
        if (in_array($value, $validValues, true)) {
            return new self($value);
        }
        return null;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
