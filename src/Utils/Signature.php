<?php

namespace Qase\PhpCommons\Utils;

class Signature
{
    /**
     * Generates a signature string in format "ids::suites::parameters"
     * 
     * @param array|null $ids List of IDs (will be ignored if empty or null)
     * @param array|null $suites List of suites (will be converted to lowercase and spaces replaced with underscores)
     * @param array|null $parameters Map of parameters (will be formatted as {"param":"value"})
     * @return string Generated signature
     */
    public static function generateSignature(?array $ids = null, ?array $suites = null, ?array $parameters = null): string
    {
        $parts = [];
        
        if (!empty($ids)) {
            $parts[] = implode('-', $ids);
        }
        
        if (!empty($suites)) {
            $normalizedSuites = array_map(function($suite) {
                return str_replace(' ', '_', strtolower(trim($suite)));
            }, $suites);
            $parts[] = implode('::', $normalizedSuites);
        }
        
        if (!empty($parameters)) {
            $paramPairs = [];
            foreach ($parameters as $key => $value) {
                $paramPairs[] = "{\"$key\":\"$value\"}";
            }
            $parts[] = implode('::', $paramPairs);
        }
        
        return implode('::', $parts);
    }
}
