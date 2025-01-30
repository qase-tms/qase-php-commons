<?php

namespace Qase\PhpCommons\Models\Config;

class ConnectionConfig
{
    public ?string $path;
    public Format $format;

    public function __construct()
    {
        $this->format = Format::json();
        $this->path = './build/qase-report';
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setFormat(string $format): void
    {
        $enumFormat = Format::fromValue($format);
        if ($enumFormat !== null) {
            $this->format = $enumFormat;
        }
    }

    public function getFormat(): ?Format
    {
        return $this->format;
    }
}
