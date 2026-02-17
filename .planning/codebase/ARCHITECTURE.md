# Architecture

**Analysis Date:** 2026-02-17

## Pattern Overview

**Overall:** Facade/Factory pattern with Strategy-based reporter selection and pluggable backends

**Key Characteristics:**
- Centralized reporter factory that abstracts backend selection
- Multiple reporter implementations (TestOps, FileReporter) sharing common interface
- Configuration-driven mode selection with fallback mechanism
- Pluggable external API clients (APIClientV1, APIClientV2)
- Stateful run management with file-based locking for concurrent test execution

## Layers

**Public API Layer:**
- Purpose: External interface for test framework integrations
- Location: `src/Interfaces/ReporterInterface.php`
- Contains: `startRun()`, `addResult()`, `completeRun()`, `sendResults()` contract
- Depends on: Nothing (pure interface)
- Used by: Test framework reporters (consumers of this library)

**Facade/Orchestration Layer:**
- Purpose: Central orchestration point that manages reporter delegation and status mapping
- Location: `src/Reporters/CoreReporter.php`
- Contains: Primary implementation of ReporterInterface with fallback logic
- Depends on: InternalReporterInterface, LoggerInterface, StatusMapping
- Used by: External reporters via ReporterFactory

**Factory/Configuration Layer:**
- Purpose: Builds reporter instances based on configuration
- Location: `src/Reporters/ReporterFactory.php`, `src/Config/ConfigLoader.php`
- Contains: Static factory methods for reporter instantiation, JSON/env config loading
- Depends on: ReporterInterface, InternalReporterInterface, QaseConfig, all reporter implementations
- Used by: Test frameworks to bootstrap the reporter

**Reporter Implementation Layer:**
- Purpose: Backend-specific test result handling and delivery
- Location: `src/Reporters/TestOpsReporter.php`, `src/Reporters/FileReporter.php`
- Contains: Concrete implementations of InternalReporterInterface
- Depends on: ClientInterface, QaseConfig, StateInterface, Models
- Used by: CoreReporter delegation

**Configuration Model Layer:**
- Purpose: Represents all configuration options as typed objects
- Location: `src/Models/Config/` (QaseConfig, TestopsConfig, ReportConfig, etc.)
- Contains: Configuration value objects with getters/setters
- Depends on: Nothing (pure data objects)
- Used by: ConfigLoader, Reporters

**Client/Integration Layer:**
- Purpose: HTTP communication with external APIs
- Location: `src/Client/ApiClientV1.php`, `src/Client/ApiClientV2.php`
- Contains: Qase API client wrappers, request serialization, authentication
- Depends on: Qase SDK clients, Models
- Used by: TestOpsReporter

**Model Layer:**
- Purpose: Domain objects representing test data
- Location: `src/Models/` (Result, Step, Attachment, Suite, etc.)
- Contains: Test result domain model with properties like execution status, steps, attachments
- Depends on: Ramsey UUID for ID generation
- Used by: Reporters, Clients, ResultSpecSerializer

**Utility Layer:**
- Purpose: Cross-cutting concerns
- Location: `src/Utils/` (StateManager, StatusMapping, HostInfo, Signature)
- Contains: Stateful run tracking, status mapping logic, host identification
- Depends on: LoggerInterface
- Used by: CoreReporter, ReporterFactory, Reporters

**Logging Layer:**
- Purpose: Centralized logging with console and file output
- Location: `src/Loggers/Logger.php`
- Contains: Info, debug, error, warning logging methods
- Depends on: Nothing
- Used by: All other layers

## Data Flow

**Test Execution & Reporting:**

1. Framework calls `ReporterFactory::create()` with optional framework/reporter identifiers
2. Factory instantiates `ConfigLoader` to load configuration from `qase.config.json` and environment variables
3. Factory creates `Logger` with debug settings and optional logging config
4. Factory instantiates both primary reporter (based on mode) and fallback reporter (based on fallback mode)
5. Factory wraps both in `CoreReporter` with `StatusMapping` utility
6. Framework calls `CoreReporter::startRun()` → delegates to primary reporter's `startRun()` → creates test run
7. For each test result:
   - Framework calls `CoreReporter::addResult(Result)`
   - CoreReporter applies status mapping to result execution status and step statuses
   - CoreReporter applies root suite prepending if configured
   - Delegates to internal reporter's `addResult()`
   - TestOpsReporter batches results and sends when batch size reached
   - FileReporter collects results for later serialization
8. Framework calls `CoreReporter::completeRun()` → calls `sendResults()` first → then calls reporter's `completeRun()`
9. If reporter fails at any point, fallback reporter is activated via `runFallbackReporter()`

**Configuration Loading:**

1. ConfigLoader reads JSON file from `getcwd()/qase.config.json` if exists
2. Parses nested objects: testops.api, testops.run, testops.plan, testops.batch, testops.configurations, report.connection
3. Overrides JSON values with environment variables (case-insensitive, `QASE_` prefix)
4. Special parsing for complex types: status filter (comma-separated), configuration values (key=value pairs), status mapping (key=value pairs)
5. Validates that testops mode has required API token and project code
6. Returns fully populated QaseConfig object

**State Management:**

1. StateManager maintains `data.json` file in `src/Utils/` directory
2. On first run: file locking ensures only one process creates the run
3. Counts concurrent test processes via reference counting
4. Only completes run when count reaches 0 (all parallel processes finished)
5. Cleans up state file when run is complete

**Status Mapping Flow:**

1. Loaded from config file (JSON object) or environment (comma-separated key=value)
2. StatusMapping validates mapping source and targets are valid statuses
3. Applied in CoreReporter BEFORE result is sent to internal reporter
4. Mapping applied to: result execution status AND all step statuses
5. Changes logged at INFO level for debugging

## Key Abstractions

**ReporterInterface:**
- Purpose: Public contract all reporters must implement
- Examples: `src/Interfaces/ReporterInterface.php`
- Pattern: Strategy pattern - interchangeable reporter implementations
- Methods: `startRun()`, `completeRun()`, `addResult(Result)`, `sendResults()`

**InternalReporterInterface:**
- Purpose: Extends ReporterInterface with internal state management
- Examples: `src/Interfaces/InternalReporterInterface.php`
- Pattern: Internal SPI (Service Provider Interface)
- Methods: Adds `getResults()`, `setResults(array)` for fallback switching

**Result Model:**
- Purpose: Immutable test result domain object with UUID
- Examples: `src/Models/Result.php` (properties: id, title, execution, steps, attachments, relations, fields, params)
- Pattern: Value Object with composition (ResultExecution, Steps, Relations)
- Properties initialized in `__construct()` with UUID generation

**ClientInterface:**
- Purpose: Abstraction for API communication
- Examples: `src/Interfaces/ClientInterface.php`
- Pattern: Adapter pattern wrapping Qase SDK clients
- Used by: Reporters to send data to Qase platform

**QaseConfig:**
- Purpose: Type-safe configuration object
- Examples: `src/Models/Config/QaseConfig.php` plus sub-configs
- Pattern: Configuration Object with nested composition
- Contains: Mode enums, TestopsConfig, ReportConfig, StatusMapping

**StateInterface:**
- Purpose: Abstract run state persistence
- Examples: `src/Interfaces/StateInterface.php`, `src/Utils/StateManager.php`
- Pattern: State pattern with file-based backend
- Methods: `startRun(callable)`, `completeRun(callable)` with callback-based execution

## Entry Points

**ReporterFactory::create():**
- Location: `src/Reporters/ReporterFactory.php::create()`
- Triggers: Framework test runner initialization
- Responsibilities: Loads configuration, instantiates logger, creates primary and fallback reporters, returns CoreReporter

**CoreReporter lifecycle:**
- Location: `src/Reporters/CoreReporter.php`
- Triggers: `startRun()` called before test suite, `addResult()` for each test, `completeRun()` after suite, `sendResults()` to flush
- Responsibilities: Orchestrates internal reporter, applies transformations (status mapping, root suite), handles fallback on errors

## Error Handling

**Strategy:** Try-catch with fallback switching and graceful degradation

**Patterns:**

- `CoreReporter::startRun()` catches exception, logs error, calls `runFallbackReporter()` to switch backends
- `CoreReporter::addResult()` applies status mapping errors are non-fatal, invalid mappings skipped with warning
- `CoreReporter::completeRun()` catches exception, logs error, attempts fallback
- `StateManager::startRun/completeRun()` throws Exception if file lock fails
- `ConfigLoader::__construct()` throws RuntimeException if JSON invalid or required fields missing in testops mode
- `FileReporter::completeRun()` throws JsonException if JSON serialization fails

## Cross-Cutting Concerns

**Logging:** All operations logged via LoggerInterface (Logger implementation)
- INFO level: High-level operations (start run, complete run, add result)
- DEBUG level: Detailed data (host info, config loaded, result JSON, status mapping applied, API calls)
- ERROR level: Exceptions and failures
- WARNING level: Invalid configuration or mappings
- Output targets: STDERR (console, supports Pest/PHPUnit formatters) and optional file log at `logs/log_YYYY-MM-DD.log`

**Validation:** Configuration loader validates:
- JSON file syntax
- Required fields for mode (testops requires token + project)
- Status mapping source/target validity
- Mode enum values

**Status Transformation:**
- Applied centrally in CoreReporter before delegation
- Covers both result execution status and individual step statuses
- Changes logged for auditability

---

*Architecture analysis: 2026-02-17*
