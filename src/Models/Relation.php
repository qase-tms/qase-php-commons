<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class Relation
{
    public ?Suite $suite;

    public function __construct()
    {
        $this->suite = new Suite();
    }

    public function addSuite(string $title): void
    {
        $this->suite->addSuite($title);
    }
}
