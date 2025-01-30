<?php

namespace Qase\PhpCommons\Models\Config;

class Format
{
    private const JSON = 'json';
    private const JSONP = 'jsonp';

    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function json(): self
    {
        return new self(self::JSON);
    }

    public static function jsonp(): self
    {
        return new self(self::JSONP);
    }

    public static function fromValue(string $value): ?self
    {
        $validValues = [self::JSON, self::JSONP];
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
