<?php

namespace Qase\PhpCommons\Models\Config;

class Batch
{
    public int $size = 200;

    public function __construct()
    {
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }
}
