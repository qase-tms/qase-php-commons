<?php

namespace Qase\PhpCommons\Models\Config;


class TestopsConfig
{
    public ?string $project = null;
    public bool $defect = false;
    public ApiConfig $api;
    public RunConfig $run;
    public PlanConfig $plan;
    public Batch $batch;
    public ConfigurationConfig $configurations;

    public function __construct()
    {
        $this->api = new ApiConfig();
        $this->run = new RunConfig();
        $this->plan = new PlanConfig();
        $this->batch = new Batch();
        $this->configurations = new ConfigurationConfig();
    }

    public function getProject(): ?string
    {
        return $this->project;
    }

    public function setProject(?string $project): void
    {
        $this->project = $project;
    }

    public function isDefect(): bool
    {
        return $this->defect;
    }

    public function setDefect(bool $defect): void
    {
        $this->defect = $defect;
    }
}
