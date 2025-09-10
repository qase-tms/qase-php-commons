<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models\Config;

class ExternalLinkType
{
    public const JIRA_CLOUD = 'jiraCloud';
    public const JIRA_SERVER = 'jiraServer';

    /**
     * Get all available external link types
     * 
     * @return array
     */
    public static function getAll(): array
    {
        return [
            self::JIRA_CLOUD,
            self::JIRA_SERVER,
        ];
    }

    /**
     * Check if the given type is valid
     * 
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::getAll(), true);
    }
}
