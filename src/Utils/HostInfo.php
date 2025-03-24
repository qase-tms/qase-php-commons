<?php

namespace Qase\PhpCommons\Utils;

use Exception;

/**
 * Class HostInfo
 * @package Utils
 */
class HostInfo
{
    /**
     * Execute a command and return its output as a string
     *
     * @param string $command Command to execute
     * @param string $default_value Default value to return on error
     * @return string Command output or default value
     */
    private function execCommand(string $command, string $default_value = ""): string
    {
        try {
            $output = [];
            $return_var = 0;
            exec($command . " 2>/dev/null", $output, $return_var);

            if ($return_var !== 0) {
                error_log("Error executing command '{$command}': Return code {$return_var}");
                return $default_value;
            }

            return implode("\n", $output);
        } catch (Exception $e) {
            error_log("Exception executing command '{$command}': " . $e->getMessage());
            return $default_value;
        }
    }

    /**
     * Get detailed OS information based on the platform
     *
     * @return string Detailed OS information
     */
    private function getDetailedOsInfo(): string
    {
        $system = strtolower(PHP_OS_FAMILY);

        try {
            if ($system === "windows") {
                return $this->execCommand("ver");
            } elseif ($system === "darwin") {
                return $this->execCommand("sw_vers -productVersion");
            } else {
                // Linux and others
                try {
                    if (file_exists("/etc/os-release")) {
                        $os_release = file_get_contents("/etc/os-release");
                        if (preg_match('/PRETTY_NAME="(.+)"/', $os_release, $matches) && isset($matches[1])) {
                            return $matches[1];
                        }
                    }
                } catch (Exception $e) {
                    // Fallback if /etc/os-release doesn't exist or can't be read
                }

                return php_uname('r');
            }
        } catch (Exception $e) {
            error_log("Error getting detailed OS info: " . $e->getMessage());
            return php_uname('r');
        }
    }

    /**
     * Get the version of a package from Composer packages
     *
     * @param string $package_name Package name to look for
     * @return string|null Package version or null if not found
     */
    private function getPackageVersion(string $package_name): ?string
    {
        try {
            // Try to get version from composer.json
            if (file_exists("composer.json")) {
                $composer_json = json_decode(file_get_contents("composer.json"), true);

                if (isset($composer_json["require"][$package_name])) {
                    return $composer_json["require"][$package_name];
                }

                if (isset($composer_json["require-dev"][$package_name])) {
                    return $composer_json["require-dev"][$package_name];
                }
            }

            // Try to get version from composer.lock
            if (file_exists("composer.lock")) {
                $composer_lock = json_decode(file_get_contents("composer.lock"), true);

                if (isset($composer_lock["packages"])) {
                    foreach ($composer_lock["packages"] as $package) {
                        if ($package["name"] === $package_name) {
                            return $package["version"];
                        }
                    }
                }

                if (isset($composer_lock["packages-dev"])) {
                    foreach ($composer_lock["packages-dev"] as $package) {
                        if ($package["name"] === $package_name) {
                            return $package["version"];
                        }
                    }
                }
            }

            return null;
        } catch (Exception $e) {
            error_log("Error getting version for package {$package_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get information about the current host environment
     *
     * @param string $framework Framework name to check
     * @param string $reporter_name Reporter name to check
     * @return array Host information
     */
    public function getHostInfo(string $framework, string $reporter_name): array
    {
        try {
            // Get PHP version
            $php_version = PHP_VERSION;

            // Get Composer version
            $composer_version = "";
            $composer_output = $this->execCommand("composer --version");
            if ($composer_output && preg_match('/Composer version (\S+)/', $composer_output, $matches)) {
                $composer_version = $matches[1];
            }

            // Get framework version
            $framework_version = $this->getPackageVersion($framework) ?? "";

            // Get reporter version
            $reporter_version = $this->getPackageVersion($reporter_name) ?? "";

            // Get commons and API client versions
            $commons_version = $this->getPackageVersion("qase/php-commons") ?? "";
            $api_client_version_v1 = $this->getPackageVersion("qase/qase-api-client") ?? "";
            $api_client_version_v2 = $this->getPackageVersion("qase/qase-api-v2-client") ?? "";

            return [
                "system" => strtolower(PHP_OS_FAMILY),
                "machineName" => gethostname() ?: "",
                "release" => php_uname('r'),
                "version" => $this->getDetailedOsInfo(),
                "arch" => php_uname('m'),
                "php" => $php_version,
                "composer" => $composer_version,
                "framework" => $framework_version,
                "reporter" => $reporter_version,
                "commons" => $commons_version,
                "apiClientV1" => $api_client_version_v1,
                "apiClientV2" => $api_client_version_v2,
            ];
        } catch (Exception $e) {
            error_log("Error getting host info: " . $e->getMessage());
            return [
                "system" => strtolower(PHP_OS_FAMILY),
                "machineName" => gethostname() ?: "",
                "release" => php_uname('r'),
                "version" => "",
                "arch" => php_uname('m'),
                "php" => "",
                "composer" => "",
                "framework" => "",
                "reporter" => "",
                "commons" => "",
                "apiClientV1" => "",
                "apiClientV2" => "",
            ];
        }
    }
}
