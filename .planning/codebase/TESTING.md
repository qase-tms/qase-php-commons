# Testing Patterns

**Analysis Date:** 2026-02-17

## Test Framework

**Runner:**
- PHPUnit 9, 10, or 11 (defined in composer.json: `"phpunit/phpunit": "^9 || ^10 || ^11"`)
- Config: `phpunit.xml`

**Assertion Library:**
- PHPUnit TestCase assertions (built-in)
- Standard assertions: `assertEquals()`, `assertTrue()`, `assertFalse()`, `assertCount()`, `assertEmpty()`, `assertContains()`, `assertSame()`

**Run Commands:**
```bash
composer test              # Run all tests (defined in composer.json scripts)
phpunit                    # Direct PHPUnit execution
phpunit --coverage-html    # Generate coverage report
```

## Test File Organization

**Location:**
- Co-located pattern: test files in `tests/` directory mirror source structure
- Source: `src/Config/ConfigLoader.php` → Test: `tests/ConfigLoaderTest.php`
- Source: `src/Utils/StatusMapping.php` → Test: `tests/StatusMappingTest.php`
- Source: `src/Reporters/CoreReporter.php` → Test: `tests/CoreReporterTest.php`

**Naming:**
- Class test files: `[ClassName]Test.php`
- Test class names: `[ClassName]Test` (extends TestCase)
- Functional test names: `Core[Feature]Test.php` for integration tests
- Example: `CoreReporterStatusMappingTest.php` tests status mapping in core reporter context

**Structure:**
```
tests/
├── ConfigLoaderTest.php              # ConfigLoader unit tests
├── ConfigLoaderStatusMappingTest.php  # ConfigLoader with status mapping integration
├── StatusMappingTest.php              # StatusMapping unit tests
├── CoreReporterTest.php               # CoreReporter unit tests
├── CoreReporterStatusMappingTest.php  # CoreReporter with status mapping
├── TestOpsReporterTest.php            # TestOpsReporter integration tests
├── ExternalLinkTest.php               # ExternalLink model tests
├── ExternalLinkApiTest.php            # External link API integration
├── ResultSpecSerializerTest.php       # Serialization tests
├── SignatureTest.php                  # Signature generation tests
├── PublicReportLinkTest.php           # Public report link tests
└── ConfigurationTest.php              # Configuration model tests
```

## Test Structure

**Suite Organization:**
```php
class ConfigLoaderTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger();
    }

    public function testConfigurationValuesFromEnv(): void
    {
        // Arrange
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES=browser=chrome,version=latest,environment=staging');

        // Act
        $configLoader = new ConfigLoader($this->logger);
        $config = $configLoader->getConfig();

        // Assert
        $this->assertTrue($config->testops->configurations->isCreateIfNotExists());
        $values = $config->testops->configurations->getValues();
        $this->assertCount(3, $values);

        // Cleanup
        putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES');
    }
}
```

**Patterns:**
- Setup method initializes test dependencies: `protected function setUp(): void`
- Private properties store mocks/fixtures for test class: `private Logger $logger`
- One assertion concept per test method: test focuses on single behavior
- Arrange-Act-Assert pattern: setup data, execute code, verify results
- Cleanup after environment-dependent tests: `putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES')`

## Mocking

**Framework:** PHPUnit built-in mocking via `$this->createMock()`

**Patterns:**
```php
// Create mock from interface
$this->clientMock = $this->createMock(ClientInterface::class);
$this->stateMock = $this->createMock(StateInterface::class);
$this->loggerMock = $this->createMock(LoggerInterface::class);

// Setup method expectations
$this->clientMock->method('isTestRunExist')
    ->with('TEST_PROJECT', 123)
    ->willReturn(false);

// Setup exception throwing
$this->primaryReporterMock->expects($this->once())
    ->method('startRun')
    ->willThrowException(new Exception('Test exception'));

// Verify call count
$this->primaryReporterMock->expects($this->once())
    ->method('startRun');

$this->fallbackReporterMock->expects($this->exactly(2))
    ->method('error')
    ->withAnyParameters();
```

**What to Mock:**
- External dependencies/interfaces: `ClientInterface`, `LoggerInterface`, `StateInterface`
- Services passed via constructor injection: mock if testing without actual service
- Example from `TestOpsReporterTest.php`: mock client, state, and logger to isolate reporter logic

**What NOT to Mock:**
- Value objects and models: use real instances
- Utility classes like `StatusMapping`: use real instances for realistic behavior
- Logger in simple unit tests: use real `Logger(false)` to capture behavior
- Configuration objects: use real `QaseConfig` to test actual structure

## Fixtures and Factories

**Test Data:**
```php
// Data provider pattern for parameterized tests
public static function booleanProvider(): array
{
    return [
        [true],
        [false],
    ];
}

// Usage with @dataProvider annotation
/**
 * @dataProvider booleanProvider
 */
public function testDebugFromEnv(bool $expected): void
{
    putenv("QASE_DEBUG=$expected");
    $configLoader = new ConfigLoader($this->logger);
    $config = $configLoader->getConfig();
    $this->assertEquals($expected, $config->getDebug());
    putenv('QASE_DEBUG');
}
```

**Location:**
- Data providers defined in test class: `public static function [nameProvider](): array`
- Reusable fixtures in setUp() method
- Test-specific mocks created in setUp()

## Coverage

**Requirements:** No coverage requirements enforced (not configured in phpunit.xml)

**View Coverage:**
```bash
phpunit --coverage-html coverage/   # HTML report
phpunit --coverage-text             # Text summary in console
```

## Test Types

**Unit Tests:**
- Scope: Test single class/method in isolation
- Dependencies: Mocked or stubbed
- Example: `StatusMappingTest` tests `StatusMapping` class with Logger mock
- Approach: Setup fixtures in setUp(), call method, assert result
- Focus: One behavior per test (one assertion concept)

**Integration Tests:**
- Scope: Test multiple components working together
- Example: `ConfigLoaderStatusMappingTest` tests ConfigLoader using real StatusMapping
- Approach: Use real instances where possible, mock external services only
- Focus: Data flow between components

**E2E Tests:**
- Not used in this codebase
- Would test against real API/storage if implemented

## Common Patterns

**Async Testing:**
Not applicable (synchronous PHP execution)

**Environment Variable Testing:**
```php
// Set environment variable for test
putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES=browser=chrome,version=latest');

// Create object under test (reads environment)
$configLoader = new ConfigLoader($this->logger);

// Verify behavior
$config = $configLoader->getConfig();
$this->assertCount(3, $config->testops->configurations->getValues());

// Always cleanup
putenv('QASE_TESTOPS_CONFIGURATIONS_VALUES');
```

**Error/Exception Testing:**
```php
// Expect exception
$this->expectException(Exception::class);

// Alternative: catch and assert
try {
    // code that should throw
} catch (Exception $e) {
    $this->assertEquals('Expected message', $e->getMessage());
}

// Mock to throw exception
$this->primaryReporterMock->expects($this->once())
    ->method('startRun')
    ->willThrowException(new Exception('Test exception'));
```

**Reflection for Private Properties:**
```php
// Access private property in test
$this->assertSame(123, $this->getPrivateProperty($reporter, 'runId'));

// Helper method (defined in test class)
private function getPrivateProperty($object, $property)
{
    $reflection = new ReflectionClass($object);
    $property = $reflection->getProperty($property);
    $property->setAccessible(true);
    return $property->getValue($object);
}
```

## Test Traits and Helpers

**Patterns:**
- No custom test base class detected
- Each test class extends `PHPUnit\Framework\TestCase` directly
- Common setup in individual test class setUp() methods
- No shared test traits observed
- Helper methods defined within test class as needed

**Example Helper:**
```php
private function createCoreReporter(?InternalReporterInterface $primaryReporter = null, ?InternalReporterInterface $fallbackReporter = null): CoreReporter
{
    $statusMapping = new StatusMapping($this->loggerMock);
    return new CoreReporter(
        $this->loggerMock,
        $primaryReporter ?? $this->primaryReporterMock,
        $fallbackReporter ?? $this->fallbackReporterMock,
        null,
        $statusMapping
    );
}
```

## Test Namespace

**Convention:**
- Test namespace mirrors source structure with `Tests\` root
- Source: `namespace Qase\PhpCommons\Config\ConfigLoader`
- Test: `namespace Tests\` (flat namespace for most tests)
- Some tests use: `namespace Qase\PhpCommons\Tests\` (matches source namespace)

**Autoloading:**
- Configured in composer.json: `"autoload-dev": { "psr-4": { "Tests\\": "tests/" } }`

## Execution Results

**Test Count:**
- 75+ test methods (from phpunit.result.cache)
- Coverage includes: configuration, status mapping, reporters, models, serialization, external links

**Example Test Namespaces:**
- `Tests\CoreReporterTest`
- `Tests\StatusMappingTest`
- `Tests\TestOpsReporterTest`
- `Qase\PhpCommons\Tests\ConfigLoaderTest`
- `Tests\ResultSpecSerializerTest`

---

*Testing analysis: 2026-02-17*
