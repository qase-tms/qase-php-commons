<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Config;
use Qase\PhpCommons\Interfaces\LoggerInterface;

class ReportConfig extends BaseConfig
{
    public function __construct(string $reporterName, LoggerInterface $logger)
    {
        parent::__construct($reporterName, $logger);
        
        $this->validate();
    }
}