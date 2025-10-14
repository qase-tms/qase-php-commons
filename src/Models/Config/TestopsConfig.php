<?php

namespace Qase\PhpCommons\Models\Config;


class TestopsConfig
{
    public ?string $project = null;
    public bool $defect = false;
    public bool $showPublicReportLink = false;
    public ApiConfig $api;
    public RunConfig $run;
    public PlanConfig $plan;
    public Batch $batch;
    public ConfigurationConfig $configurations;
    public array $statusFilter = [];

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

    public function getStatusFilter(): array
    {
        return $this->statusFilter;
    }

    public function setStatusFilter(array $statusFilter): void
    {
        $this->statusFilter = $statusFilter;
    }

    public function isShowPublicReportLink(): bool
    {
        return $this->showPublicReportLink;
    }

    public function setShowPublicReportLink(bool $showPublicReportLink): void
    {
        $this->showPublicReportLink = $showPublicReportLink;
    }
}
