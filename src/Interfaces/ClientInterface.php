<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Interfaces;

use Qase\PhpCommons\Models\Attachment;


interface ClientInterface
{
    public function isProjectExist(string $code): bool;

    public function getEnvironment(string $code, string $envName): ?int;

    public function createTestRun(string $code, string $title, ?string $description = null, ?int $planId = null, ?int $envId = null): int;

    public function completeTestRun(string $code, int $runId): void;

    public function isTestRunExist(string $code, int $runId): bool;

    public function uploadAttachment(string $code, Attachment $attachment): ?string;

    public function sendResults(string $code, int $runId, array $results): void;
}
