<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

use Ramsey\Uuid\Uuid;

class Result extends BaseModel
{
    public string $id;
    public string $title = '';
    public ?string $signature = null;
    public ?array $testOpsIds = null;
    public ResultExecution $execution;
    public array $fields = [];
    public array $attachments = [];
    public array $steps = [];
    public array $params = [];
    public array $groupParams = [];
    public Relation $relations;
    public string $message = '';

    public function __construct()
    {
        $this->id = Uuid::uuid4()->__toString();
        $this->relations = new Relation();
        $this->execution = new ResultExecution();
    }
}
