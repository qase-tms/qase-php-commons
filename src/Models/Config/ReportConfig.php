<?php

namespace Qase\PhpCommons\Models\Config;

class ReportConfig
{
    public ?Driver $driver = null;
    public ConnectionConfig $connection;

    public function __construct()
    {
        $this->driver = Driver::local();
        $this->connection = new ConnectionConfig();
    }

    public function setDriver(string $driver): void
    {
        $enumDriver = Driver::fromValue($driver);
        if ($enumDriver !== null) {
            $this->driver = $enumDriver;
        }
    }

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }
}
