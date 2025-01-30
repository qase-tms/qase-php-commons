<?php

namespace Qase\PhpCommons\Models\Config;

class PlanConfig
{
    public ?int $id = null;

    public function __construct()
    {
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
