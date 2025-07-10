<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class ConfigurationGroup
{
    public ?int $id = null;
    public ?string $title = null;
    public array $items = [];

    public function __construct(?int $id = null, ?string $title = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->items = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }
} 
