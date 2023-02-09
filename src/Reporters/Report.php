<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;
use Qase\PhpCommons\Config\ReportConfig;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Interfaces\ReporterInterface;

class Report implements ReporterInterface
{
    protected array $results = [];

    private ReportConfig $config;

    public function __construct(ReportConfig $config) 
    {
        $this->config = $config;
    }

    public function startRun() 
    {

    }

    public function completeRun()
    {

    }

    public function addResult(Result $result) 
    {
        
    }
}