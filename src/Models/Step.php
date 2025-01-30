<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

use Ramsey\Uuid\Uuid;

class Step extends BaseModel
{
    public string $id;
    public StepData $data;
    public StepExecution $execution;
    public array $attachments = [];
    public array $steps = [];
    public string $stepType;
    public ?string $parentId = null;

    public function __construct(string $stepType = 'text')
    {
        $this->id = Uuid::uuid4()->__toString();
        $this->data = new StepData();
        $this->execution = new StepExecution();
        $this->stepType = $stepType;
    }
}
