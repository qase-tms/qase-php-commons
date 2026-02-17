# Technology Stack

**Analysis Date:** 2026-02-17

## Languages

**Primary:**
- PHP 8.0+ - Main language for the library and all source code

## Runtime

**Environment:**
- PHP 8.0+ (minimum requirement specified in composer.json)
- Composer for dependency management

**Package Manager:**
- Composer 2.x
- Lockfile: `composer.lock` present (102 KB, comprehensive dependency lock)

## Frameworks

**Core:**
- No web framework - This is a standalone SDK library
- PSR-4 autoloading standard for namespace organization

**Testing:**
- PHPUnit 9, 10, or 11 - Test framework (dev dependency)
- Configuration: `phpunit.xml` (bootstrap: `vendor/autoload.php`)
- Run command: `composer test` (runs `phpunit`)

**Build/Dev:**
- Composer scripts for testing and package management

## Key Dependencies

**Critical (Runtime):**
- `qase/qase-api-client` ^1.1.5 - Qase API v1 client library
  - Provides REST API access to Qase TestOps platform
  - Required for: Projects, Runs, Results, Attachments, Configurations, Environments
  - Uses GuzzleHTTP 7.3+ under the hood

- `qase/qase-api-v2-client` ^1.1.2 - Qase API v2 client library
  - Newer API version for advanced result submission
  - Provides batch result submission and enhanced data models
  - Uses same HTTP client layer as v1

- `ramsey/uuid` ^4.7 - UUID generation library
  - Provides RFC 4122 compliant UUID generation and validation
  - Used for unique identifiers in test reporting

**Infrastructure (Transitive):**
- `guzzlehttp/guzzle` ^7.3 - HTTP client library
  - Used by both API client libraries for all REST API calls
  - Provides robust HTTP request/response handling

- `guzzlehttp/psr7` ^1.7 || ^2.0 - PSR-7 HTTP message implementation
  - Handles HTTP requests and responses for GuzzleHTTP

- `brick/math` ^0.8 - Arbitrary precision arithmetic
  - Transitive dependency from ramsey/uuid
  - Required for UUID library operations

- `ramsey/collection` ^1.2 || ^2.0 - Collection library
  - Transitive dependency from ramsey/uuid
  - Provides collection utilities for UUID operations

## Configuration

**Environment:**
- Configuration loaded from either `qase.config.json` file (in project root) or environment variables
- Environment variables prefixed with `QASE_` override file-based configuration
- `src/Config/ConfigLoader.php` - Handles loading and merging configurations

**Build:**
- `composer.json` - Package definition and dependency requirements
- `composer.lock` - Locked versions for reproducible installations
- `phpunit.xml` - PHPUnit configuration for test execution
- Configuration options: `optimize-autoloader`, `preferred-install: dist`

## Platform Requirements

**Development:**
- PHP 8.0 or higher
- Composer installed and accessible
- ext-curl - Required by Guzzle for HTTP requests
- ext-json - Required for JSON encoding/decoding
- ext-mbstring - Required for multibyte string handling

**Production:**
- PHP 8.0 or higher runtime
- Network access to Qase API endpoints (api.qase.io or enterprise instance)
- Required PHP extensions: curl, json, mbstring

## External API Communication

**HTTP Client Stack:**
```
GuzzleHTTP 7.3+ (HTTP client)
  ├── PSR-7 (HTTP message interfaces)
  ├── cURL extension (transport)
  └── JSON encoding (responses)
```

**Qase API Endpoints:**
- Default: `https://api.qase.io/v1` (API v1) and `https://api.qase.io/v2` (API v2)
- Enterprise: `https://api-{host}/v1` and `https://api-{host}/v2` (configurable)
- Authentication: Token-based (Bearer token in headers)

---

*Stack analysis: 2026-02-17*
