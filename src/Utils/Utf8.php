<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Utils;

/**
 * The API client encodes requests with plain json_encode, so a single invalid
 * UTF-8 byte in any result would fail the whole batch.
 */
class Utf8
{
    public static function scrub(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::scrubString($value);
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[is_string($key) ? self::scrubString($key) : $key] = self::scrub($item);
            }

            return $clean;
        }

        return $value;
    }

    private static function scrubString(string $value): string
    {
        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_scrub')) {
            return mb_scrub($value, 'UTF-8');
        }

        return (string)iconv('UTF-8', 'UTF-8//IGNORE', $value);
    }
}
