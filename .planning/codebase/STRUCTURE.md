# Codebase Structure

**Analysis Date:** 2026-02-17

## Directory Layout

```
qase-php-commons/
├── src/                              # Source code (PSR-4: Qase\PhpCommons\)
│   ├── Client/                       # API client wrappers
│   ├── Collections/                  # Collection models
│   ├── Config/                       # Configuration loading logic
│   ├── Interfaces/                   # Public and internal SPIs
│   ├── Loggers/                      # Logging implementation
│   ├── Models/                       # Domain models and data objects
│   │   ├── Config/                   # Configuration value objects
│   │   └── FileReporter/             # File-based report models
│   ├── Reporters/                    # Reporter implementations and factory
│   └── Utils/                        # Utilities (state, status mapping, host info)
│
├── tests/                            # Unit and integration tests
│   ├── *Test.php                     # Test files (PHPUnit)
│   └── (no subdirectories)           # Flat structure
│
├── vendor/                           # Composer dependencies
├── logs/                             # Runtime logs (created at runtime)
├── .planning/                        # GSD planning documents
├── composer.json                     # PHP package manifest
├── composer.lock                     # Dependency lock file
├── phpunit.xml                       # PHPUnit configuration
├── README.md                         # Project documentation
└── CLAUDE.md                         # Project-specific Claude instructions
```

## Directory Purposes

**src/Client:**
- Purpose: Wrappers around Qase SDK API clients for communication
- Contains: ApiClientV1, ApiClientV2 extending functionality
- Key files: `ApiClientV1.php`, `ApiClientV2.php`
- Responsibility: HTTP requests, authentication, result serialization to API format

**src/Collections:**
- Purpose: Collection/container models for grouped data
- Contains: ResultCollection
- Responsibility: Holds and manipulates sets of results

**src/Config:**
- Purpose: Configuration loading from multiple sources
- Contains: ConfigLoader (reads JSON and environment variables)
- Key files: `ConfigLoader.php`, `BaseConfig.php`
- Responsibility: Aggregate configuration from qase.config.json + env vars, validate, return QaseConfig object

**src/Interfaces:**
- Purpose: Public and internal service provider interfaces
- Contains: ReporterInterface (public), InternalReporterInterface, ClientInterface, LoggerInterface, StateInterface
- Key files: `ReporterInterface.php`, `InternalReporterInterface.php`
- Responsibility: Define contracts for extensibility

**src/Loggers:**
- Purpose: Logging implementation
- Contains: Logger class implementing LoggerInterface
- Key files: `Logger.php`
- Responsibility: Output messages to STDERR (colored) and optional file log

**src/Models:**
- Purpose: Domain models representing test data and configuration
- Contains: Result, Step, Suite, Attachment, Relation, ResultExecution
- Key files: `BaseModel.php` (magic getter/setter), `Result.php`
- Responsibility: Data representation with UUID generation, property access

**src/Models/Config:**
- Purpose: Configuration value objects
- Contains: QaseConfig (root), TestopsConfig, ReportConfig, nested configs like ApiConfig, RunConfig
- Key files: `QaseConfig.php`, `TestopsConfig.php`, `ReportConfig.php`
- Responsibility: Type-safe configuration storage with defaults and enums (Mode, Driver, Format)

**src/Models/FileReporter:**
- Purpose: JSON report structure models
- Contains: Run, RunExecution, RunStats, ShortResult
- Responsibility: Models for local file-based test report generation

**src/Reporters:**
- Purpose: Reporter implementations and factory
- Contains: CoreReporter (facade), TestOpsReporter, FileReporter, ReporterFactory, ResultSpecSerializer
- Key files: `ReporterFactory.php`, `CoreReporter.php`, `TestOpsReporter.php`, `FileReporter.php`
- Responsibility: Primary orchestration (CoreReporter), specific reporter logic, factory instantiation

**src/Utils:**
- Purpose: Cross-cutting utilities
- Contains: StateManager (file-based run state), StatusMapping (status transformation), HostInfo (environment detection), Signature (result signing)
- Key files: `StateManager.php`, `StatusMapping.php`
- Responsibility: State persistence with locking, status conversion, host metadata collection

**tests:**
- Purpose: Unit and integration tests
- Contains: PHPUnit test files (flat, no subdirectories)
- Key files: `CoreReporterTest.php`, `ConfigLoaderTest.php`, `TestOpsReporterTest.php`, `StatusMappingTest.php`
- Responsibility: Verify reporter behavior, configuration loading, status mapping

## Key File Locations

**Entry Points:**
- `src/Reporters/ReporterFactory.php`: Factory for creating reporter instances (call `ReporterFactory::create()`)
- `src/Reporters/CoreReporter.php`: Primary reporter facade that manages lifecycle

**Configuration:**
- `src/Config/ConfigLoader.php`: Loads qase.config.json and environment variables
- `src/Models/Config/QaseConfig.php`: Root configuration object

**Core Logic:**
- `src/Reporters/CoreReporter.php`: Orchestration, status mapping, fallback switching
- `src/Reporters/TestOpsReporter.php`: TestOps backend implementation with batching
- `src/Reporters/FileReporter.php`: Local file-based report generation

**Testing:**
- `tests/CoreReporterTest.php`: Core reporter behavior
- `tests/ConfigLoaderTest.php`: Configuration loading from JSON and env
- `tests/StatusMappingTest.php`: Status transformation logic

## Naming Conventions

**Files:**
- Reporters: `{Name}Reporter.php` (e.g., `CoreReporter.php`, `TestOpsReporter.php`)
- Models: `{Name}.php` (e.g., `Result.php`, `Suite.php`)
- Interfaces: `{Name}Interface.php` (e.g., `ReporterInterface.php`)
- Utilities: `{Name}.php` (e.g., `StateManager.php`, `StatusMapping.php`)
- Tests: `{ComponentName}Test.php` (e.g., `CoreReporterTest.php`, `ConfigLoaderTest.php`)

**Classes:**
- PSR-4 namespace: `Qase\PhpCommons\{Directory\ClassName}`
- Example: `src/Reporters/CoreReporter.php` → `Qase\PhpCommons\Reporters\CoreReporter`

**Methods:**
- camelCase: `startRun()`, `addResult()`, `completeRun()` (public interface)
- Private methods: snake_case helpers like `runFallbackReporter()`, `applyStatusMapping()`
- Getters: `get{PropertyName}()` (e.g., `getConfig()`, `getResults()`)
- Setters: `set{PropertyName}()` (e.g., `setMode()`, `setToken()`)

**Variables:**
- camelCase: `$logger`, `$reporter`, `$statusMapping`
- Private properties: Lowercase with underscore for magic property access in BaseModel

**Constants:**
- UPPER_CASE: `PREFIX = '[Qase]'`, `JSON_ERROR_NONE`
- Enums: Classes in `src/Models/Config/` (e.g., `Mode::OFF`, `Mode::TESTOPS`, `Driver::LOCAL`)

## Where to Add New Code

**New Reporter Backend (e.g., CloudReporter):**
1. Create `src/Reporters/CloudReporter.php` implementing `InternalReporterInterface`
2. Implement: `startRun()`, `completeRun()`, `addResult()`, `sendResults()`, `getResults()`, `setResults()`
3. Update `ReporterFactory::createInternalReporter()` to instantiate when mode == 'cloud'
4. Add configuration object to `src/Models/Config/` if needed (e.g., `CloudConfig.php`)
5. Add test: `tests/CloudReporterTest.php`

**New Configuration Option:**
1. Add property to relevant config class in `src/Models/Config/` (e.g., `QaseConfig.php` or `TestopsConfig.php`)
2. Add getter/setter method
3. Update `ConfigLoader::loadFromJsonFile()` to parse from JSON
4. Update `ConfigLoader::overrideWithEnvVariables()` to parse from env var
5. Update README.md with configuration table
6. Add test in `tests/ConfigLoaderTest.php`

**New Utility/Helper:**
1. Create `src/Utils/{UtilityName}.php`
2. Implement needed interface or standalone utility
3. Inject into reporters/factory via constructor
4. Add test: `tests/{UtilityName}Test.php`

**New Domain Model:**
1. Create `src/Models/{ModelName}.php` extending `BaseModel` or standalone
2. Define public properties with PHPDoc comments
3. Add constructor if UUID or special initialization needed
4. Use in reporters or other models

## Special Directories

**logs/:**
- Purpose: Runtime logging destination
- Generated: Yes (created at runtime if logging.file enabled)
- Committed: No (in .gitignore)
- Location: `logs/log_YYYY-MM-DD.log` (one per day)

**vendor/:**
- Purpose: Composer dependencies
- Generated: Yes (by `composer install`)
- Committed: No (in .gitignore)
- Contains: Qase API clients, Ramsey UUID, testing frameworks

**src/Utils/:**
- Purpose: Helper utilities and state management
- Special file: `data.json` - runtime state file for concurrent test tracking (created on first run, deleted when complete)
- Not committed: data.json is temporary and cleaned up

**.planning/:**
- Purpose: GSD planning and analysis documents
- Generated: Yes (by `/gsd:map-codebase` commands)
- Contains: ARCHITECTURE.md, STRUCTURE.md, CONVENTIONS.md, TESTING.md, STACK.md, INTEGRATIONS.md, CONCERNS.md

---

*Structure analysis: 2026-02-17*
