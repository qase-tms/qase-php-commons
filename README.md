# Qase PHP Commons

This module is an SDK for developing test reporters for Qase TMS.
You should use it if you're developing your own test reporter for a special-purpose framework.

To report results from tests using a popular framework or test runner,
don't install this module directly and
use the corresponding reporter module instead:

* [PHPUnit](https://github.com/qase-tms/qase-phpunit)

## Installation

```bash
composer require qase/php-commons
```

## Configuration

Qase PHP Reporters can be configured in multiple ways:

* using a config file `qase.config.json`
* using environment variables

All configuration options are listed in the table below:

| Description                                                                                                                | Config file                | Environment variable            | Default value                           | Required | Possible values            |
|----------------------------------------------------------------------------------------------------------------------------|----------------------------|---------------------------------|-----------------------------------------|----------|----------------------------|
| **Common**                                                                                                                 |                            |                                 |                                         |          |                            |
| Mode of reporter                                                                                                           | `mode`                     | `QASE_MODE`                     | `off`                                   | No       | `testops`, `report`, `off` |
| Fallback mode of reporter                                                                                                  | `fallback`                 | `QASE_FALLBACK`                 | `off`                                   | No       | `testops`, `report`, `off` |
| Environment slug                                                                                                           | `environment`              | `QASE_ENVIRONMENT`              | undefined                               | No       | Any string                 |
| Root suite                                                                                                                 | `rootSuite`                | `QASE_ROOT_SUITE`               | undefined                               | No       | Any string                 |
| Enable debug logs                                                                                                          | `debug`                    | `QASE_DEBUG`                    | `False`                                 | No       | `True`, `False`            |
| Enable console logging                                                                                                     | `logging.console`          | `QASE_LOGGING_CONSOLE`          | `True`                                  | No       | `True`, `False`            |
| Enable file logging                                                                                                        | `logging.file`             | `QASE_LOGGING_FILE`             | `True`                                  | No       | `True`, `False`            |
| **Qase Report configuration**                                                                                              |                            |                                 |                                         |          |                            |
| Driver used for report mode                                                                                                | `report.driver`            | `QASE_REPORT_DRIVER`            | `local`                                 | No       | `local`                    |
| Path to save the report                                                                                                    | `report.connection.path`   | `QASE_REPORT_CONNECTION_PATH`   | `./build/qase-report`                   |          |                            |
| Local report format                                                                                                        | `report.connection.format` | `QASE_REPORT_CONNECTION_FORMAT` | `json`                                  |          | `json`, `jsonp`            |
| **Qase TestOps configuration**                                                                                             |                            |                                 |                                         |          |                            |
| Token for [API access](https://developers.qase.io/#authentication)                                                         | `testops.api.token`        | `QASE_TESTOPS_API_TOKEN`        | undefined                               | Yes      | Any string                 |
| Qase API host. For enterprise users, specify address: `example.qase.io`                                           | `testops.api.host`         | `QASE_TESTOPS_API_HOST`         | `qase.io`                               | No       | Any string                 |
| Qase enterprise environment                                                                                                | `testops.api.enterprise`   | `QASE_TESTOPS_API_ENTERPRISE`   | `False`                                 | No       | `True`, `False`            |
| Code of your project, which you can take from the URL: `https://app.qase.io/project/DEMOTR` - `DEMOTR` is the project code | `testops.project`          | `QASE_TESTOPS_PROJECT`          | undefined                               | Yes      | Any string                 |
| Qase test run ID                                                                                                           | `testops.run.id`           | `QASE_TESTOPS_RUN_ID`           | undefined                               | No       | Any integer                |
| Qase test run title                                                                                                        | `testops.run.title`        | `QASE_TESTOPS_RUN_TITLE`        | `Automated run <Current date and time>` | No       | Any string                 |
| Qase test run description                                                                                                  | `testops.run.description`  | `QASE_TESTOPS_RUN_DESCRIPTION`  | `<Framework name> automated run`        | No       | Any string                 |
| Qase test run complete                                                                                                     | `testops.run.complete`     | `QASE_TESTOPS_RUN_COMPLETE`     | `True`                                  |          | `True`, `False`            |
| Qase test run tags | `testops.run.tags`         | `QASE_TESTOPS_RUN_TAGS`         | undefined                               | No       | Config: array of strings, Env: comma-separated string |
| External issue type | `testops.run.externalLink.type` | `QASE_TESTOPS_RUN_EXTERNAL_LINK_TYPE` | undefined                               | No | `jiraCloud`, `jiraServer` |
| External issue URL | `testops.run.externalLink.link` | `QASE_TESTOPS_RUN_EXTERNAL_LINK_URL` | undefined                               | No | Any valid URL string |
| Qase test plan ID                                                                                                          | `testops.plan.id`          | `QASE_TESTOPS_PLAN_ID`          | undefined                               | No       | Any integer                |
| Size of batch for sending test results                                                                                     | `testops.batch.size`       | `QASE_TESTOPS_BATCH_SIZE`       | `200`                                   | No       | Any integer                |
| Enable defects for failed test cases                                                                                       | `testops.defect`           | `QASE_TESTOPS_DEFECT`           | `False`                                 | No       | `True`, `False`            |
| Enable public report link generation after run completion                                                                 | `testops.showPublicReportLink` | `QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK` | `False`                                 | No       | `True`, `False`            |
| Configuration values to associate with test run                                                                            | `testops.configurations.values` | `QASE_TESTOPS_CONFIGURATIONS_VALUES` | `[]`                                    | No       | Comma-separated key=value pairs |
| Create configuration groups and values if they don't exist                                                                | `testops.configurations.createIfNotExists` | `QASE_TESTOPS_CONFIGURATIONS_CREATE_IF_NOT_EXISTS` | `False`                                 | No       | `True`, `False`            |
| Status filter for test results                                                                                             | `testops.statusFilter`     | `QASE_TESTOPS_STATUS_FILTER`     | `[]`                                    | No       | Comma-separated string       |
| Status mapping for test results                                                                                            | `statusMapping`           | `QASE_STATUS_MAPPING`            | `{}`                                    | No       | JSON object or comma-separated key=value pairs |

### Example `qase.config.json` config

```json
{
  "mode": "testops",
  "fallback": "report",
  "debug": false,
  "environment": "local",
  "captureLogs": false,
  "statusMapping": {
    "invalid": "failed",
    "skipped": "passed"
  },
  "report": {
    "driver": "local",
    "connection": {
      "local": {
        "path": "./build/qase-report",
        "format": "json"
      }
    }
  },
  "testops": {
    "api": {
      "token": "<token>",
      "host": "qase.io"
    },
    "run": {
      "title": "Regress run",
      "description": "Regress run description",
      "complete": true,
      "tags": ["tag1", "tag2"],
      "externalLink": {
        "type": "jiraCloud",
        "link": "PROJ-123"
      }
    },
    "defect": false,
    "showPublicReportLink": true,
    "project": "<project_code>",
    "batch": {
      "size": 100
    },
    "configurations": {
      "values": [
        {
          "name": "browser",
          "value": "chrome"
        },
        {
          "name": "version",
          "value": "latest"
        },
        {
          "name": "environment",
          "value": "staging"
        }
      ],
      "createIfNotExists": true
    },
    "statusFilter": ["skipped", "blocked", "untested"]
  }
}
```

### Environment Variables Example

You can also configure configurations using environment variables:

```bash
export QASE_TESTOPS_CONFIGURATIONS_VALUES="browser=chrome,version=latest,environment=staging"
export QASE_TESTOPS_CONFIGURATIONS_CREATE_IF_NOT_EXISTS=true
export QASE_TESTOPS_STATUS_FILTER="skipped,blocked,untested"
export QASE_TESTOPS_RUN_EXTERNAL_LINK_TYPE="jiraCloud"
export QASE_TESTOPS_RUN_EXTERNAL_LINK_URL="PROJ-123"
export QASE_TESTOPS_SHOW_PUBLIC_REPORT_LINK=true
export QASE_STATUS_MAPPING="invalid=failed,skipped=passed"
```

The `QASE_TESTOPS_CONFIGURATIONS_VALUES` should be a comma-separated list of key=value pairs.

### How Configurations Work

Configurations in Qase TestOps work as follows:

* **name** field represents the configuration group (e.g., "browser", "environment")
* **value** field represents the configuration item within that group (e.g., "chrome", "staging")
* When `createIfNotExists` is true, the system will:
  1. Create a configuration group with the specified name if it doesn't exist
  2. Create a configuration item with the specified value in that group
  3. Associate the configuration item ID with the test run

### Status Filtering

You can filter out test results with specific statuses using the `statusFilter` configuration option:

**Config file example:**

```json
{
  "testops": {
    "statusFilter": ["skipped", "blocked", "untested"]
  }
}
```

**Environment variable example:**

```bash
export QASE_TESTOPS_STATUS_FILTER="skipped,blocked,untested"
```

**Available statuses:**

* `passed` - Test passed successfully
* `failed` - Test failed
* `skipped` - Test was skipped
* `blocked` - Test was blocked
* `untested` - Test was not tested

When `statusFilter` is configured, results with the specified statuses will be excluded from being sent to Qase TestOps.

### Status Mapping

You can map (transform) test result statuses using the `statusMapping` configuration option. This is useful when you need to convert statuses from your testing framework to match Qase's expected statuses.

**Config file example:**

```json
{
  "statusMapping": {
    "invalid": "failed",
    "skipped": "passed"
  }
}
```

**Environment variable example:**

```bash
export QASE_STATUS_MAPPING="invalid=failed,skipped=passed"
```

**Available statuses for mapping:**

* `passed` - Test passed successfully
* `failed` - Test failed due to assertion error
* `skipped` - Test was skipped
* `blocked` - Test was blocked
* `invalid` - Test failed due to non-assertion error (network issues, syntax errors)

**How status mapping works:**

* Status mapping is applied **before** status filtering
* Mapping is applied to both test results and individual test steps
* Invalid mappings are ignored with a warning message
* Mapping is applied centrally for all reporters regardless of testing framework
* Changes are logged for debugging purposes

**Example scenarios:**

1. **Convert invalid to failed**: Map `invalid` status to `failed` to treat infrastructure issues as test failures
2. **Convert skipped to passed**: Map `skipped` status to `passed` to include skipped tests in pass rate calculations
3. **Multiple mappings**: Apply different mappings for different statuses in a single configuration

**Validation rules:**

* Source and target statuses must be valid (from the list above)
* Source and target cannot be the same (redundant mappings are ignored)
* Case-sensitive validation (e.g., `PASSED` is invalid, use `passed`)
* Non-string values are ignored

### External Issue Integration

You can link test runs to external issues (like Jira tickets) by configuring the `externalLink` option:

**Config file example:**

```json
{
  "testops": {
    "run": {
      "externalLink": {
        "type": "jiraCloud",
        "link": "PROJ-123"
      }
    }
  }
}
```

**Environment variables example:**

```bash
export QASE_TESTOPS_RUN_EXTERNAL_LINK_TYPE="jiraCloud"
export QASE_TESTOPS_RUN_EXTERNAL_LINK_URL="PROJ-123"
```

**Supported external issue types:**

* `jiraCloud` - For Jira Cloud instances
* `jiraServer` - For Jira Server/Data Center instances

When an external link is configured, the system will automatically associate the test run with the specified external issue after the run is created.

**Technical Details:**

* External issue functionality uses the Qase API v1 client
* The system automatically maps internal enum values to API enum values:
  * `jiraCloud` → `jira-cloud`
  * `jiraServer` → `jira-server`
* API calls are made using the official Qase API client models (`RunexternalIssues` and `RunexternalIssuesLinksInner`)
