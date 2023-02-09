<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class Step extends BaseModel
{
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_INVALID = 'invalid';


    protected string $title;

    protected string $status;

    protected string $comment = '';

    protected string $stacktrace = '';

    protected array $attachments = [];

    protected array $steps = [];

    protected int $duration = 0;

    protected int $completed_at;

    public function __construct(string $title)
    {
        $this->title = $title;
    }
}