<?php

namespace Qase\PhpCommons\Models\Config;

class RunConfig
{
    public string $title;
    public ?string $description = null;
    public bool $complete = true;
    public ?int $id = null;

    public function __construct()
    {
        $this->title = 'Automated Run ' . date('Y-m-d H:i:s');
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function setComplete(bool $complete): void
    {
        $this->complete = $complete;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }
}
