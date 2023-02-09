<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class Attachment extends BaseModel
{
    protected string $title;

    protected string $mime;

    protected int $size;

    protected ?string $content = null;

    protected ?string $path = null;

    public function __construct(string $title, string $mime, int $size, string $content, string $path)
    {
        $this->title = $title;
        $this->mime = $mime;
        $this->size = $size;
        $this->content = $content;
        $this->path = $path;
    }
}