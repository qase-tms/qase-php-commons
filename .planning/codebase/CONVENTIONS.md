# Coding Conventions

**Analysis Date:** 2026-02-17

## Naming Patterns

**Files:**
- PascalCase for class files: `Logger.php`, `ConfigLoader.php`, `StatusMapping.php`
- Match class name exactly to filename
- One class per file (PSR-4 compliant)

**Classes:**
- PascalCase: `ConfigLoader`, `StatusMapping`, `CoreReporter`, `ResultExecution`
- Interface names end with `Interface`: `LoggerInterface`, `ReporterInterface`, `StateInterface`, `ClientInterface`
- Abstract classes use prefix `Base`: `BaseModel`, `BaseConfig`

**Functions/Methods:**
- camelCase for all methods: `getConfig()`, `setMapping()`, `parseFromEnv()`, `mapStatus()`, `addResult()`
- Private methods use lowercase prefix `_` or full camelCase: `validateMapping()`, `overrideWithEnvVariables()`, `writeLog()`
- Methods follow verb-noun pattern: `startRun()`, `completeRun()`, `sendResults()`, `addResult()`
- Getter methods use `get` prefix: `getConfig()`, `getToken()`, `getHost()`, `getMapping()`
- Setter methods use `set` prefix: `setToken()`, `setHost()`, `setMapping()`, `setStatus()`
- Boolean checks use `is` prefix: `isEmpty()`, `isValidStatus()`, `isDefect()`, `isComplete()`

**Variables/Properties:**
- camelCase for local variables: `$configLoader`, `$mapping`, `$statusMapping`, `$logEntry`
- Private properties declared with `private` visibility: `private string $filePath`, `private ?string $tempExternalLinkType`
- Nullable properties use `?` type hint: `private ?string $rootSuite`, `private ?InternalReporterInterface $reporter`

**Constants:**
- UPPER_SNAKE_CASE for constants: `PREFIX`, `LEVEL_COLORS`, `VALID_STATUSES`
- Class-level constants use `const` keyword: `private const PREFIX = '[Qase]'`
- Valid value arrays documented as constants: `public const VALID_STATUSES = ['passed', 'failed', 'skipped', 'blocked', 'invalid']`

**Type Hints:**
- Full namespace in type hints where needed: `\JsonSerializable`, `\InvalidArgumentException`
- Use scalar type hints: `string`, `bool`, `int`, `array`
- Use nullable types: `?string`, `?InternalReporterInterface`
- Array type hints with documentation: `@var array<string, string>` for associative arrays

## Code Style

**Formatting:**
- 4-space indentation (PHP standard)
- No trailing whitespace
- One blank line between methods
- Opening braces on same line as declarations

**Declarations:**
- `declare(strict_types=1);` at top of all source files (not in tests)
- Example: `<?php\n\ndeclare(strict_types=1);\n\nnamespace Qase\PhpCommons\Loggers;`
- Namespace declaration immediately after declare statement
- Use statements grouped after namespace

**Linting:**
- No explicit linting configuration detected
- Code follows PSR-12 standard conventions (implied)
- Classes must implement interfaces they declare

## Import Organization

**Order:**
1. Top-level use statements for interfaces
2. Exception imports
3. Implementation imports in alphabetical order
4. Model/Domain imports

**Example from `ConfigLoader.php`:**
```php
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Models\Config\QaseConfig;
use Qase\PhpCommons\Utils\StatusMapping;
use RuntimeException;
```

**Path Aliases:**
- No aliases detected; full qualified namespace paths used throughout
- PSR-4 autoloading: `Qase\PhpCommons\` maps to `src/`
- Test autoloading: `Tests\` maps to `tests/`

## Error Handling

**Patterns:**
- Throw specific exception types: `RuntimeException`, `Exception`, `InvalidArgumentException`, `LogicException`
- Example from `ConfigLoader.php` line 38: `throw new RuntimeException("TestOps mode requires API token and project to be set")`
- Example from `StatusMapping.php` line 136: `throw new InvalidArgumentException("Invalid source status '$source' in mapping...")`
- Try-catch blocks used for graceful fallback: `HostInfo.php` catches exceptions and continues execution
- Core reporter catches exceptions and triggers fallback reporter: `CoreReporter.php` lines 50-55
- Error messages use string concatenation or sprintf for context

**Validation:**
- Configuration validation in constructor: `ConfigLoader::__construct()` calls `$this->validate()`
- Input validation before processing: `StatusMapping::validateMapping()` checks both source and target
- Type checking with `is_string()`: `StatusMapping` line 130 validates mapping keys/values
- Case-sensitive validation: `in_array($source, self::VALID_STATUSES, true)` with strict flag

## Logging

**Framework:** Console and file logging via custom `Logger` class in `src/Loggers/Logger.php`

**Patterns:**
- Four log levels: `info()`, `debug()`, `error()`, `warning()`
- Debug logs only output when debug mode enabled: `Logger::debug()` checks `$this->debug` flag
- Console output to STDERR for PHPUnit/Pest compatibility: `fwrite(STDERR, $formattedMessage)`
- Colored output for console: RED for ERROR, YELLOW for WARNING, GREEN for INFO, BLUE for DEBUG
- File logging with daily rotation: `log_' . date('Y-m-d') . '.log'`
- Log entries include timestamp: `date('Y-m-d H:i:s')`
- Logger instantiated early and passed to other classes via dependency injection

**Usage Examples:**
```php
$this->logger->info('Starting test run');
$this->logger->debug("Status mapping applied: '$status' -> '$mappedStatus'");
$this->logger->error('Failed to start reporter: ' . $e->getMessage());
$this->logger->warning($message);
```

## Comments

**When to Comment:**
- Complex parsing logic: `ConfigLoader::parseConfigurationValues()` has inline comments explaining comma-separated parsing
- Workarounds and temporary solutions: `ConfigLoader::parseExternalLinkType()` line 272-274 explains temporary storage pattern
- Non-obvious business logic: `ApiConfig::maskString()` comments when token masking is applied
- Magic numbers: `ApiConfig::maskString()` line 48 explains length comparison logic

**JSDoc/TSDoc:**
- Used for public methods: `StatusMapping::parseFromEnv()` line 41-55
- Parameter documentation with `@param` tag: `@param string $value Comma-separated key=value pairs`
- Return type documentation with `@return`: `@return string Mapped status or original if no mapping exists`
- Array type documentation: `@var array<string, string> Status mapping configuration`
- Array of arrays: `@var array<string, string>` for mapping configuration

**Example Documentation:**
```php
/**
 * Parse configuration values from comma-separated key=value pairs
 *
 * @param string $value Comma-separated key=value pairs
 */
private function parseConfigurationValues(string $value): void
{
    // implementation
}
```

## Function Design

**Size:**
- Small, focused functions: most methods 5-20 lines
- ConfigLoader parsing methods 10-30 lines due to complexity
- Maximum 350 lines seen in ApiClientV1 (wraps external API)

**Parameters:**
- Constructor injection pattern: classes receive dependencies in constructor
- Example: `CoreReporter::__construct()` receives logger, reporters, root suite, status mapping
- Methods use type hints for all parameters: `public function mapStatus(string $status): void`
- Optional parameters use `?type` syntax: `?string $rootSuite = null`

**Return Values:**
- Explicit return types on all public methods: `void`, `string`, `array`, `bool`
- Private methods also use return types
- Void methods for side effects: `startRun(): void`, `addResult($result): void`
- String/array methods return computed values: `mapStatus(): string`, `getMapping(): array`

## Module Design

**Exports:**
- Classes exposed via PSR-4 autoloading
- Interfaces defined for all major contracts: `ReporterInterface`, `LoggerInterface`, `ClientInterface`
- Models use public properties for data access: `Result` class exposes `$id`, `$title`, `$status`, etc.
- Configuration classes use getter/setter pattern: `ApiConfig::getToken()`, `ApiConfig::setToken()`

**Barrel Files:**
- No barrel/index files detected
- Direct class imports used throughout codebase
- Autoloading handles namespace-to-file mapping

## Type System

**Property Types:**
- All properties have explicit type declarations
- Typed class properties: `public string $title = ''`
- Nullable properties: `public ?string $signature = null`
- Array types: `public array $fields = []`
- Object types: `public ResultExecution $execution`
- Property types match PHPDoc: `/** @var array<string, string> */ private array $mapping = []`

**Strict Types:**
- Strict types enforced at file level with `declare(strict_types=1)`
- Applies to type checking in source code, not in test files
- Enables strict return type checking and parameter type checking

---

*Convention analysis: 2026-02-17*
