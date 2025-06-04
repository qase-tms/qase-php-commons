<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Utils\Signature;

class SignatureTest extends TestCase
{
    /**
     * @dataProvider signatureProvider
     */
    public function testGenerateSignature(
        ?array $ids,
        ?array $suites,
        ?array $parameters,
        string $expected
    ): void {
        $result = Signature::generateSignature($ids, $suites, $parameters);
        $this->assertEquals($expected, $result);
    }

    public function signatureProvider(): array
    {
        return [
            'empty signature' => [
                null,
                null,
                null,
                ''
            ],
            'only ids' => [
                [1, 2, 3],
                null,
                null,
                '1-2-3'
            ],
            'only suites' => [
                null,
                ['My Suite', 'Another Suite'],
                null,
                'my_suite::another_suite'
            ],
            'only parameters' => [
                null,
                null,
                ['param1' => 'value1', 'param2' => 'value2'],
                '{"param1":"value1"}::{"param2":"value2"}'
            ],
            'ids and suites' => [
                [1, 2],
                ['My Suite', 'Another Suite'],
                null,
                '1-2::my_suite::another_suite'
            ],
            'ids and parameters' => [
                [1, 2],
                null,
                ['param1' => 'value1', 'param2' => 'value2'],
                '1-2::{"param1":"value1"}::{"param2":"value2"}'
            ],
            'suites and parameters' => [
                null,
                ['My Suite', 'Another Suite'],
                ['param1' => 'value1', 'param2' => 'value2'],
                'my_suite::another_suite::{"param1":"value1"}::{"param2":"value2"}'
            ],
            'full signature' => [
                [1, 2],
                ['My Suite', 'Another Suite'],
                ['param1' => 'value1', 'param2' => 'value2'],
                '1-2::my_suite::another_suite::{"param1":"value1"}::{"param2":"value2"}'
            ],
            'empty arrays' => [
                [],
                [],
                [],
                ''
            ],
            'suites with spaces' => [
                null,
                ['My Suite Name', 'Another Suite Name'],
                null,
                'my_suite_name::another_suite_name'
            ],
            'suites with mixed case' => [
                null,
                ['My SUITE', 'ANOTHER Suite'],
                null,
                'my_suite::another_suite'
            ]
        ];
    }
} 
