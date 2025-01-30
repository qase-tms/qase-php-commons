<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class Suite
{
    public array $data = [];

    public function __construct()
    {
        $this->data = [];
    }

    public function addSuite(string $title): void
    {
        $this->data[] = new SuiteData($title);
    }
}
