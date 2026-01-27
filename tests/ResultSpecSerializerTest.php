<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Models\Attachment;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Models\Step;
use Qase\PhpCommons\Reporters\ResultSpecSerializer;

/**
 * Ensures result JSON matches qase-tms/specs report/schemas/result.yaml (snake_case, schema field names).
 */
class ResultSpecSerializerTest extends TestCase
{
    private ResultSpecSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ResultSpecSerializer();
    }

    public function testOutputUsesSnakeCaseAndSchemaFieldNames(): void
    {
        $result = new Result();
        $result->id = 'test-uuid';
        $result->title = 'Test title';
        $result->execution->setStatus('passed');
        $result->execution->setThread('main');
        $result->relations->addSuite('Root');

        $data = $this->serializer->toSpecArray($result);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('muted', $data);
        $this->assertArrayHasKey('signature', $data);
        $this->assertArrayHasKey('fields', $data);
        $this->assertArrayHasKey('params', $data);
        $this->assertArrayHasKey('param_groups', $data);
        $this->assertArrayHasKey('testops_id', $data);
        $this->assertArrayHasKey('testops_ids', $data);
        $this->assertArrayHasKey('execution', $data);
        $this->assertArrayHasKey('attachments', $data);
        $this->assertArrayHasKey('steps', $data);
        $this->assertArrayHasKey('relations', $data);

        $this->assertArrayNotHasKey('groupParams', $data);
        $this->assertArrayNotHasKey('testOpsIds', $data);

        $exec = $data['execution'];
        $this->assertArrayHasKey('status', $exec);
        $this->assertArrayHasKey('start_time', $exec);
        $this->assertArrayHasKey('end_time', $exec);
        $this->assertArrayHasKey('duration', $exec);
        $this->assertArrayHasKey('stacktrace', $exec);
        $this->assertArrayHasKey('thread', $exec);
        $this->assertArrayNotHasKey('startTime', $exec);
        $this->assertArrayNotHasKey('stackTrace', $exec);
    }

    public function testAttachmentUsesSchemaFields(): void
    {
        $result = new Result();
        $result->id = 'r1';
        $result->title = 'T';
        $result->attachments[] = Attachment::createContentAttachment('file.txt', 'content', 'text/plain');

        $data = $this->serializer->toSpecArray($result);
        $this->assertCount(1, $data['attachments']);
        $att = $data['attachments'][0];
        $this->assertArrayHasKey('file_name', $att);
        $this->assertArrayHasKey('file_path', $att);
        $this->assertArrayHasKey('mime_type', $att);
        $this->assertArrayHasKey('content', $att);
        $this->assertSame('file.txt', $att['file_name']);
        $this->assertSame('text/plain', $att['mime_type']);
        $this->assertSame(base64_encode('content'), $att['content']);
    }

    public function testStepUsesSnakeCase(): void
    {
        $step = new Step('text');
        $step->id = 'step-1';
        $step->data->setAction('Click');
        $step->data->setExpectedResult('OK');
        $step->execution->setStatus('passed');

        $result = new Result();
        $result->id = 'r1';
        $result->title = 'T';
        $result->steps[] = $step;

        $data = $this->serializer->toSpecArray($result);
        $this->assertCount(1, $data['steps']);
        $s = $data['steps'][0];
        $this->assertArrayHasKey('step_type', $s);
        $this->assertSame('text', $s['step_type']);
        $this->assertArrayHasKey('data', $s);
        $this->assertArrayHasKey('expected_result', $s['data']);
        $this->assertArrayHasKey('execution', $s);
        $this->assertArrayHasKey('start_time', $s['execution']);
    }

    public function testRelationsSuiteDataHasPublicId(): void
    {
        $result = new Result();
        $result->id = 'r1';
        $result->title = 'T';
        $result->relations->addSuite('Root');

        $data = $this->serializer->toSpecArray($result);
        $this->assertIsArray($data['relations']);
        $this->assertArrayHasKey('suite', $data['relations']);
        $this->assertArrayHasKey('data', $data['relations']['suite']);
        $suiteData = $data['relations']['suite']['data'];
        $this->assertNotEmpty($suiteData);
        $this->assertArrayHasKey('title', $suiteData[0]);
        $this->assertArrayHasKey('public_id', $suiteData[0]);
    }

    public function testEmptyFieldsAndParamsAreObjects(): void
    {
        $result = new Result();
        $result->id = 'r1';
        $result->title = 'T';

        $data = $this->serializer->toSpecArray($result);
        $this->assertInstanceOf(\stdClass::class, $data['fields']);
        $this->assertInstanceOf(\stdClass::class, $data['params']);
    }

    public function testParamsWithEmptyValueBecomeEmptyString(): void
    {
        $result = new Result();
        $result->id = 'r1';
        $result->title = 'T';
        $result->params = [
            ['name' => 'a', 'value' => 'x'],
            ['name' => 'b', 'value' => null],
            ['name' => 'c', 'value' => ''],
        ];

        $data = $this->serializer->toSpecArray($result);
        $this->assertIsArray($data['params']);
        $this->assertSame([['name' => 'a', 'value' => 'x'], ['name' => 'b', 'value' => 'empty'], ['name' => 'c', 'value' => 'empty']], $data['params']);
    }

    public function testParamGroupsWithEmptyValuesBecomeEmptyString(): void
    {
        $result = new Result();
        $result->id = 'r1';
        $result->title = 'T';
        $result->groupParams = [
            ['a', 'b'],
            [null, ''],
            ['x', null],
        ];

        $data = $this->serializer->toSpecArray($result);
        $this->assertSame([['a', 'b'], ['empty', 'empty'], ['x', 'empty']], $data['param_groups']);
    }
}
