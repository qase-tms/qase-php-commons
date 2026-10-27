<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Utils\Utf8;

class Utf8Test extends TestCase
{
    /**
     * @dataProvider validStrings
     */
    public function testValidStringsAreReturnedUnchanged(string $value): void
    {
        $this->assertSame($value, Utf8::scrub($value));
    }

    public static function validStrings(): array
    {
        return [
            'ascii' => ['plain title'],
            'empty' => [''],
            'em dash' => ['issues a challenge — OTP emailed'],
            'ellipsis and quotes' => ['waits… for “quoted” ’text’'],
            'arrow' => ['Auth → Login'],
            'cyrillic' => ['проверка входа'],
            'emoji' => ['ships 🚀'],
        ];
    }

    /**
     * @dataProvider malformedStrings
     */
    public function testMalformedStringsBecomeValidUtf8(string $value, string $keptText): void
    {
        $clean = Utf8::scrub($value);

        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
        $this->assertStringContainsString($keptText, $clean);
        $this->assertNotFalse(json_encode($clean));
    }

    public static function malformedStrings(): array
    {
        return [
            'em dash tail split mid-character' => ["\x80\x94 OTP emailed", ' OTP emailed'],
            'truncated leading byte' => ["title \xE2", 'title '],
            'latin-1 byte' => ["caf\xE9 test", ' test'],
            'overlong encoding' => ["a\xC0\xAFb", 'a'],
        ];
    }

    public function testArraysAreScrubbedRecursivelyIncludingKeys(): void
    {
        $clean = Utf8::scrub([
            "key\xE2" => "value\x80",
            'nested' => ['ok — fine', ["deep\xFF"]],
            'number' => 42,
        ]);

        $this->assertNotFalse(json_encode($clean));
        $this->assertSame('ok — fine', $clean['nested'][0]);
        $this->assertSame(42, $clean['number']);
        $this->assertCount(3, $clean);
    }

    public function testNonStringScalarsAndNullPassThrough(): void
    {
        $this->assertNull(Utf8::scrub(null));
        $this->assertSame(7, Utf8::scrub(7));
        $this->assertSame(1.5, Utf8::scrub(1.5));
        $this->assertTrue(Utf8::scrub(true));
    }
}
