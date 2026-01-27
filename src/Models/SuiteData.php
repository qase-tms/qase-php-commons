<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class SuiteData
{
    public ?string $title;
    public ?int $publicId;

    public function __construct(?string $title, ?int $publicId = null)
    {
        $this->title = $title;
        $this->publicId = $publicId;
    }
}
