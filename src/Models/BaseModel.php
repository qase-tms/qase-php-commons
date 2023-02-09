<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;

class BaseModel {

    public function __get($key)
    {
        return $this->$key;
    }

    public function __set($key, $value)
    {
        $this->$key = $value;
    }
}