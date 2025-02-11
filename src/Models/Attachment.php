<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;


final class Attachment extends BaseModel
{
    public ?string $title;
    public ?string $mime;
    public ?string $content;
    public ?string $path;

    private function __construct(?string $title = null, ?string $content = null, ?string $mime = null, ?string $path = null)
    {
        $this->title = $title;
        $this->content = $content;
        $this->mime = $mime;
        $this->path = $path;
    }

    public static function createFileAttachment(string $path): self
    {
        return new self(path: $path);
    }

    public static function createContentAttachment(string $title, string $content, ?string $mimeType = null): self
    {
        return new self(title: $title, content: $content, mime: $mimeType);
    }
}
