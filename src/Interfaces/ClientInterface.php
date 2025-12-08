<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Interfaces;

use Qase\PhpCommons\Models\Attachment;
use Qase\PhpCommons\Models\ConfigurationGroup;
use Qase\PhpCommons\Models\ConfigurationItem;


interface ClientInterface
{
    public function isProjectExist(string $code): bool;

    public function getEnvironment(string $code, string $envName): ?int;

    public function createTestRun(string $code, string $title, ?string $description = null, ?int $planId = null, ?int $envId = null, ?array $tags = null, ?array $configurations = null): int;

    public function completeTestRun(string $code, int $runId): void;

    public function isTestRunExist(string $code, int $runId): bool;

    /**
     * Upload one or multiple attachments
     * 
     * Limitations:
     * - Up to 32 MB per file
     * - Up to 128 MB per single request
     * - Up to 20 files per single request
     * 
     * @param string $code Project code
     * @param Attachment|Attachment[] $attachments Single attachment or array of attachments
     * @return string|string[]|null Hash(es) of uploaded attachment(s) or null on failure
     */
    public function uploadAttachment(string $code, Attachment|array $attachments): string|array|null;

    public function sendResults(string $code, int $runId, array $results): void;

    /**
     * Get configuration groups for a project
     * 
     * @param string $code Project code
     * @return ConfigurationGroup[]
     */
    public function getConfigurationGroups(string $code): array;

    /**
     * Create a configuration group
     * 
     * @param string $code Project code
     * @param string $title Group title
     * @return ConfigurationGroup|null
     */
    public function createConfigurationGroup(string $code, string $title): ?ConfigurationGroup;

    /**
     * Create a configuration item
     * 
     * @param string $code Project code
     * @param int $groupId Group ID
     * @param string $title Item title
     * @return ConfigurationItem|null
     */
    public function createConfigurationItem(string $code, int $groupId, string $title): ?ConfigurationItem;

    /**
     * Update external issue for a test run
     * 
     * @param string $code Project code
     * @param string $type External issue type
     * @param array $links Array of links with run_id and external_issue
     * @return void
     */
    public function runUpdateExternalIssue(string $code, string $type, array $links): void;

    /**
     * Enable public report for a test run
     * 
     * @param string $code Project code
     * @param int $runId Test run ID
     * @return string|null Public report link or null on failure
     */
    public function enablePublicReport(string $code, int $runId): ?string;
}
