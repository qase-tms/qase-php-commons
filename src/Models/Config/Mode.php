<?php

namespace Qase\PhpCommons\Models\Config;

class Mode
{
    const TESTOPS = 'testops';
    const REPORT = 'report';
    const OFF = 'off';

    public static function isValid(string $value): bool
    {
        return in_array($value, [self::TESTOPS, self::REPORT, self::OFF], true);
    }
}
