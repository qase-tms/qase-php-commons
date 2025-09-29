<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Utils;

use Qase\PhpCommons\Interfaces\LoggerInterface;

/**
 * Utility class for mapping test result statuses
 */
class StatusMapping
{
    /**
     * Valid statuses that can be mapped
     */
    public const VALID_STATUSES = [
        'passed',
        'failed', 
        'skipped',
        'blocked',
        'invalid'
    ];

    /**
     * @var array<string, string> Status mapping configuration
     */
    private array $mapping = [];

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Set status mapping from array
     * 
     * @param array<string, string> $mapping
     */
    public function setMapping(array $mapping): void
    {
        $this->mapping = $this->validateMapping($mapping);
    }

    /**
     * Parse status mapping from environment variable format
     * Format: "invalid=failed,skipped=passed"
     * 
     * @param string $envValue
     */
    public function parseFromEnv(string $envValue): void
    {
        if (empty(trim($envValue))) {
            $this->mapping = [];
            return;
        }

        $mapping = [];
        $pairs = array_map('trim', explode(',', $envValue));

        foreach ($pairs as $pair) {
            if (strpos($pair, '=') === false) {
                $this->logger->error("Invalid status mapping pair format: '$pair'. Expected format: 'source=target'");
                continue;
            }

            list($source, $target) = explode('=', $pair, 2);
            $source = trim($source);
            $target = trim($target);

            if (!empty($source) && !empty($target)) {
                $mapping[$source] = $target;
            }
        }

        $this->mapping = $this->validateMapping($mapping);
    }

    /**
     * Apply status mapping to a status
     * 
     * @param string $status Original status
     * @return string Mapped status or original if no mapping exists
     */
    public function mapStatus(string $status): string
    {
        if (isset($this->mapping[$status])) {
            $mappedStatus = $this->mapping[$status];
            $this->logger->debug("Status mapping applied: '$status' -> '$mappedStatus'");
            return $mappedStatus;
        }

        return $status;
    }

    /**
     * Get current mapping configuration
     * 
     * @return array<string, string>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * Check if mapping is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->mapping);
    }

    /**
     * Validate mapping configuration
     * 
     * @param array<string, string> $mapping
     * @return array<string, string> Validated mapping
     */
    private function validateMapping(array $mapping): array
    {
        $validatedMapping = [];

        foreach ($mapping as $source => $target) {
            if (!is_string($source) || !is_string($target)) {
                $this->logger->error("Status mapping keys and values must be strings. Skipping: '$source' => '$target'");
                continue;
            }

            if (!in_array($source, self::VALID_STATUSES, true)) {
                $this->logger->error("Invalid source status '$source' in mapping. Valid statuses: " . implode(', ', self::VALID_STATUSES));
                continue;
            }

            if (!in_array($target, self::VALID_STATUSES, true)) {
                $this->logger->error("Invalid target status '$target' in mapping. Valid statuses: " . implode(', ', self::VALID_STATUSES));
                continue;
            }

            if ($source === $target) {
                $this->logger->error("Redundant mapping: '$source' => '$target'. Source and target are the same.");
                continue;
            }

            $validatedMapping[$source] = $target;
        }

        return $validatedMapping;
    }

    /**
     * Get all valid statuses
     * 
     * @return array<string>
     */
    public static function getValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Check if a status is valid
     * 
     * @param string $status
     * @return bool
     */
    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES, true);
    }
}
