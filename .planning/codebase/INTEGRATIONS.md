# External Integrations

**Analysis Date:** 2026-02-17

## APIs & External Services

**Qase TestOps Platform:**
- Main SaaS service for test result management and reporting
  - SDK: `qase/qase-api-client` (v1) and `qase/qase-api-v2-client` (v2)
  - Auth: `QASE_TESTOPS_API_TOKEN` (Bearer token authentication)
  - Endpoints:
    - Projects: Check project existence, retrieve project data
    - Test Runs: Create, complete, retrieve, update test runs
    - Results: Submit test results and execution data (via API v2)
    - Attachments: Upload test evidence files
    - Configurations: Create/retrieve test configurations (browsers, environments, etc.)
    - Environments: Query and link test environments
    - External Issues: Link test runs to external issue trackers

**External Issue Tracking Integration:**
- Jira Cloud - Link test runs to Jira Cloud issues
  - Config: `testops.run.externalLink.type` = `jiraCloud`
  - Reference: `testops.run.externalLink.link` (Jira ticket ID, e.g., PROJ-123)
  - Implementation: `src/Client/ApiClientV1.php::runUpdateExternalIssue()`

- Jira Server/Data Center - Link test runs to on-premise Jira instances
  - Config: `testops.run.externalLink.type` = `jiraServer`
  - Reference: `testops.run.externalLink.link` (Jira ticket ID)
  - Implementation: `src/Client/ApiClientV1.php::runUpdateExternalIssue()`

## Data Storage

**Databases:**
- None directly - This is a reporting SDK without persistent storage
- Results are sent to Qase TestOps platform (handled via API)

**File Storage:**
- Local filesystem (for local report mode):
  - Default path: `./build/qase-report`
  - Configurable: `QASE_REPORT_CONNECTION_PATH`
  - Format: JSON or JSONP
  - Implementation: `src/Reporters/FileReporter.php`

- Attachment uploads to Qase:
  - Temporary file handling in `src/Client/ApiClientV1.php::uploadAttachment()`
  - Limits:
    - Per file: 32 MB
    - Per request: 128 MB
    - Per batch: 20 files
  - Cleanup: Temporary files are automatically deleted after upload

**Caching:**
- None - Stateless SDK, no caching layer

## Authentication & Identity

**Auth Provider:**
- Qase TestOps API token (custom implementation)
- Token-based authentication using Bearer scheme
- Token sourced from:
  - Config file: `testops.api.token`
  - Environment variable: `QASE_TESTOPS_API_TOKEN`
- Implementation: `src/Config/ConfigLoader.php`, `src/Client/ApiClientV1.php`

**Approach:**
- Token validated in `Configuration::getDefaultConfiguration()` from API clients
- Token passed as `Token` header in all API requests
- No refresh token mechanism (single long-lived token)

## Monitoring & Observability

**Error Tracking:**
- None integrated - Errors are logged locally

**Logs:**
- Two output channels:
  - Console logging (STDERR by design to support Pest output formatter)
  - File logging (optional, configurable)
- Configuration:
  - Console: `QASE_LOGGING_CONSOLE` (true/false)
  - File: `QASE_LOGGING_FILE` (true/false)
  - Debug mode: `QASE_DEBUG` enables verbose logging
- Implementation: `src/Loggers/Logger.php` (outputs to STDERR for console)

## CI/CD & Deployment

**Hosting:**
- Not a deployable application - This is a library/SDK
- Distributed via Composer package repository (Packagist)
- Consumer: Reporters and test frameworks integrate this SDK

**CI Pipeline:**
- GitHub Actions (inferred from `.github/` directory)
- Tests run via PHPUnit: `composer test`
- Version: 2.1.13 (current)

## Environment Configuration

**Required env vars:**
- `QASE_TESTOPS_API_TOKEN` - API token for Qase platform access (required for testops mode)
- `QASE_TESTOPS_PROJECT` - Project code in Qase (e.g., `DEMOTR`, required for testops mode)

**Optional env vars (common):**
```
QASE_MODE                              # testops | report | off (default: off)
QASE_FALLBACK                          # Fallback mode if primary fails
QASE_ENVIRONMENT                       # Test environment slug
QASE_ROOT_SUITE                        # Root test suite name
QASE_DEBUG                             # Enable debug logging (true/false)
QASE_LOGGING_CONSOLE                   # Console output (true/false)
QASE_LOGGING_FILE                      # File output (true/false)
```

**Optional env vars (TestOps):**
```
QASE_TESTOPS_API_HOST                  # Qase API host (default: qase.io, enterprise: example.qase.io)
QASE_TESTOPS_RUN_ID                    # Existing run ID to append results
QASE_TESTOPS_RUN_TITLE                 # Test run title
QASE_TESTOPS_RUN_DESCRIPTION           # Test run description
QASE_TESTOPS_RUN_COMPLETE              # Auto-complete run (true/false)
QASE_TESTOPS_RUN_TAGS                  # Comma-separated tags
QASE_TESTOPS_RUN_EXTERNAL_LINK_TYPE    # jiraCloud or jiraServer
QASE_TESTOPS_RUN_EXTERNAL_LINK_URL     # External issue ID (e.g., PROJ-123)
QASE_TESTOPS_PLAN_ID                   # Test plan ID
QASE_TESTOPS_BATCH_SIZE                # Results batch size (default: 200)
QASE_TESTOPS_DEFECT                    # Enable defect tracking
QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK   # Generate public report link
QASE_TESTOPS_CONFIGURATIONS_VALUES     # Comma-separated key=value pairs (e.g., browser=chrome,version=latest)
QASE_TESTOPS_CONFIGURATIONS_CREATE_IF_NOT_EXISTS  # Auto-create config groups/items
QASE_TESTOPS_STATUS_FILTER             # Comma-separated statuses to exclude
QASE_STATUS_MAPPING                    # Status mapping (e.g., invalid=failed,skipped=passed)
```

**Optional env vars (Reports):**
```
QASE_REPORT_DRIVER                     # local (default)
QASE_REPORT_CONNECTION_PATH            # Report output path (default: ./build/qase-report)
QASE_REPORT_CONNECTION_FORMAT          # json or jsonp (default: json)
```

**Secrets location:**
- API tokens stored in environment variables (CI/CD secrets)
- Configuration file `qase.config.json` should be .gitignored if containing tokens
- No built-in secrets vault - relies on environment/platform secrets management

## Webhooks & Callbacks

**Incoming:**
- None - This is a SDK library, not a webhook receiver

**Outgoing:**
- Public report link generation (optional)
  - Endpoint: PATCH `https://api.qase.io/v1/run/{project}/{runId}/public`
  - Response: Contains public report URL
  - Implementation: `src/Client/ApiClientV1.php::enablePublicReport()`
  - Used when `testops.showPublicReportLink` is enabled

## Configuration Precedence

**Order of precedence (highest to lowest):**
1. Environment variables (QASE_* prefix)
2. Configuration file (`qase.config.json` in working directory)
3. Default values

**Example - Loading token:**
```php
// In ConfigLoader::overrideWithEnvVariables()
// If QASE_TESTOPS_API_TOKEN env var exists, it overrides qase.config.json value
// If neither exists, default is empty string (may cause validation error in testops mode)
```

## API Rate Limiting

**No built-in protection:**
- Rate limiting handled by Qase API server
- Batch size configurable: `QASE_TESTOPS_BATCH_SIZE` (default: 200)
- Recommendations:
  - Set reasonable batch sizes for large test suites
  - Monitor API responses for 429 (Too Many Requests) status codes

## Data Models & Serialization

**Supported test result statuses:**
- passed - Test passed
- failed - Test failed due to assertion
- skipped - Test was skipped
- blocked - Test was blocked
- invalid - Test failed due to non-assertion error

**Status mapping feature:**
- Transform test result statuses from framework format to Qase format
- Configuration: `QASE_STATUS_MAPPING` or `statusMapping` in config file
- Format: `source=target` (e.g., `invalid=failed`)
- Applied before status filtering
- Implementation: `src/Utils/StatusMapping.php`

**Configuration management:**
- Configuration groups and items can be auto-created if not found
- Useful for browsers, environments, versions, etc.
- Control: `testops.configurations.createIfNotExists`

---

*Integration audit: 2026-02-17*
