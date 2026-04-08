# Tags Support for PHP Reporters — Design Specification

## Overview

Add support for test-level tags that are sent to Qase API v2 as a comma-separated string in `ResultCreateFields.tags`. Tags can be applied via PHP 8 attributes (`#[Tags("smoke", "regression")]`), fluent API (Pest), or `#[Field("tags", "...")]` fallback.

## Scope

Four repositories:
- **qase-php-commons** (this repo) — Result model + serializer
- **qase-phpunit** — Attribute, parser, examples, tests, docs, expected YAML
- **qase-pest** — Attribute, parser, fluent API, examples, tests, docs (new), expected YAML
- **qase-codeception** — Attribute, parser, examples, tests, docs, expected YAML

Gherkin/BDD tag parsing (`@QaseTags:`) is **out of scope** — the codeception reporter has no Gherkin tag parsing infrastructure today.

## Reference

- C# implementation: [qase-csharp#53](https://github.com/qase-tms/qase-csharp/pull/53)
- API field: `ResultCreateFields.tags` — nullable string, comma-separated tag titles

---

## 1. qase-php-commons

### 1.1 Result Model

**File:** `src/Models/Result.php`

Add property:

```php
public array $tags = [];
```

Position: after `public array $fields = []` (line 16), before `public array $attachments = []`.

### 1.2 ResultSpecSerializer

**File:** `src/Reporters/ResultSpecSerializer.php`

In `toSpecArray()`, add `tags` field to the output array. The serializer handles two sources of tags:

1. **Direct tags** from `$result->tags` (populated by `#[Tags]` attribute)
2. **Fields fallback** — if `$result->fields` contains a key `"tags"`, extract its value, split by comma, trim, merge into tags, and remove the key from fields

Processing order in `toSpecArray()`:
1. Extract tags from fields fallback (if key exists)
2. Merge with `$result->tags`
3. Deduplicate via `array_unique`
4. Serialize as comma-separated string (or `null` if empty)

```php
// In toSpecArray(), before building $data:
$tags = $result->tags;
$fields = $result->fields;

if (isset($fields['tags']) && is_string($fields['tags'])) {
    $fieldTags = array_map('trim', explode(',', $fields['tags']));
    $fieldTags = array_filter($fieldTags, fn($t) => $t !== '');
    $tags = array_merge($tags, $fieldTags);
    unset($fields['tags']);
}

$uniqueTags = array_values(array_unique($tags));

// In $data array:
'tags' => $uniqueTags === [] ? null : implode(',', $uniqueTags),
'fields' => $fields === [] ? new \stdClass() : $fields,
```

Position: `tags` field placed after `fields` in the output array.

### 1.3 Unit Tests

**File:** `tests/ResultSpecSerializerTest.php`

Add tests:
- `testTagsEmpty` — empty tags → `null`
- `testTagsFromResult` — `$result->tags = ['smoke', 'regression']` → `"smoke,regression"`
- `testTagsFromFieldsFallback` — `$result->fields['tags'] = 'smoke,regression'` → tags serialized, key removed from fields
- `testTagsMergedAndDeduplicated` — tags from both sources, duplicates removed
- `testTagsTrimsWhitespace` — field tags with spaces are trimmed

### 1.4 Version

`composer.json` version: `2.1.17` → `2.1.18`

---

## 2. qase-phpunit

### 2.1 New Attribute Files

**`src/Attributes/TagsAttributeInterface.php`:**
```php
<?php

namespace Qase\PHPUnitReporter\Attributes;

interface TagsAttributeInterface extends AttributeInterface
{
    public function getTags(): array;
}
```

**`src/Attributes/Tags.php`:**
```php
<?php

namespace Qase\PHPUnitReporter\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Tags implements TagsAttributeInterface
{
    private array $tags;

    public function __construct(string ...$values)
    {
        $this->tags = $values;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
```

### 2.2 Metadata Model

**File:** `src/Models/Metadata.php`

Add: `public array $tags = [];`

### 2.3 AttributeParser

**File:** `src/Attributes/AttributeParser.php`

In `getMetadataFromAnnotations()`, add after the Field handling block:

```php
if ($annotation instanceof TagsAttributeInterface) {
    $metadata->tags = array_merge($metadata->tags, $annotation->getTags());
}
```

This automatically accumulates class-level + method-level tags since `$annotations` is the merged array of both.

### 2.4 QaseReporter

**File:** `src/QaseReporter.php`

In `startTest()`, after setting fields, add:

```php
$testResult->tags = $metadata->tags;
```

### 2.5 Example Updates

**`example/AttributeTest.php`:**
- Add `#[Tags("smoke")]` to the class level
- Add `#[Tags("regression")]` to `testWithAllAttributes` method
- Add import: `use Qase\PHPUnitReporter\Attributes\Tags;`

### 2.6 Expected YAML

**`expected/phpunit-examples.yaml`:**

For all tests in AttributeTest (class-level tag "smoke"):
- Add `tags:\n  - smoke` after `fields:` (or after `status:` if no fields)

For `testWithAllAttributes` (Full attributes test) — class + method tags:
- Add `tags:\n  - smoke\n  - regression`

Update `run.stats.total` if test count changes (currently 22 — stays 22, no new tests added).

### 2.7 Unit Tests

**New file:** `tests/AttributeParserTest.php`

Tests:
- `testParseTagsFromSingleAttribute` — `#[Tags("smoke", "regression")]` → `['smoke', 'regression']`
- `testParseTagsFromMultipleAttributes` — two `#[Tags]` on same method → accumulated
- `testMergeClassAndMethodTags` — class `#[Tags("smoke")]` + method `#[Tags("regression")]` → `['smoke', 'regression']`
- `testClassTagsInheritedByMethodWithoutTags` — class `#[Tags("smoke")]`, method has no tags → `['smoke']`
- `testEmptyTags` — no Tags attributes → `[]`
- `testAllAttributesTogether` — QaseId + Title + Field + Suite + Tags → all Metadata properties populated

### 2.8 Documentation

**`docs/usage.md`:**

1. Add `- [Tags](#tags)` to Table of Contents (before `[Parameter]` line, after `[Field]`)
2. Add `### Tags` section after `### Field`:

```markdown
### Tags

Adds tags to the test case in Qase. Tags from class and method levels are merged.

**Target:** Method and Class
**Repeatable:** Yes

\`\`\`php
// Single attribute with multiple tags
#[Tags('smoke', 'regression')]
public function testUserLogin(): void
{
    // Test implementation
}

// Multiple Tags attributes
#[
    Tags('smoke'),
    Tags('regression')
]
public function testUserLogin(): void
{
    // Test implementation
}
\`\`\`

#### Class-Level and Method-Level Merge

\`\`\`php
#[Tags('smoke')]
class AuthenticationTest extends TestCase
{
    #[Tags('regression')]
    public function testLogin(): void
    {
        // This test will have both 'smoke' and 'regression' tags
    }

    public function testLogout(): void
    {
        // This test will have only 'smoke' tag (inherited from class)
    }
}
\`\`\`
```

3. Update Combined Metadata Example — add `Tags('smoke')` after `Suite('Smoke Tests')`
4. Update Class-Level Configuration example — add `Tags('smoke')` to class, update comments

### 2.9 Version

`composer.json` version: `2.1.8` → `2.1.9`
`composer.json` dependency: `qase/php-commons` → `^2.1.18`

---

## 3. qase-pest

### 3.1 New Attribute Files

Same pattern as phpunit but under `Qase\PestReporter\Attributes` namespace:

- `src/Attributes/TagsAttributeInterface.php`
- `src/Attributes/Tags.php`

Identical structure to phpunit versions, different namespace.

### 3.2 Metadata Model

**File:** `src/Models/Metadata.php`

Add: `public array $tags = [];`

### 3.3 AttributeParser

**File:** `src/Attributes/AttributeParser.php`

Same addition as phpunit: handle `TagsAttributeInterface` in `getMetadataFromAnnotations()`.

### 3.4 QaseReporter — Attribute Flow

**File:** `src/QaseReporter.php`

In `startTest()`, after setting fields, add:

```php
$testResult->tags = $metadata->tags;
```

### 3.5 QaseReporter — Fluent API

**File:** `src/QaseReporter.php`

Add method:

```php
public function tag(string ...$tags): self
{
    $key = $this->getCurrentTestKey();
    if ($key !== null && isset($this->testResults[$key])) {
        $this->testResults[$key]->tags = array_merge(
            $this->testResults[$key]->tags,
            $tags
        );
    }
    return $this;
}
```

### 3.6 QaseReporterInterface

**File:** `src/QaseReporterInterface.php`

No change needed — fluent methods are not in the interface.

### 3.7 Static Facade

**File:** `src/Qase.php`

Add method:

```php
public static function tag(string ...$tags): void
{
    $qr = QaseReporter::getInstanceWithoutInit();
    if (!$qr) {
        return;
    }
    $qr->tag(...$tags);
}
```

### 3.8 NullQaseReporter

**File:** `src/NullQaseReporter.php`

Add no-op method:

```php
public function tag(string ...$tags): self
{
    return $this;
}
```

### 3.9 Example Updates

**`example/tests/Feature/QaseIdTest.php`:**

Add a test with tags via fluent API:

```php
it('uses tags', function () {
    qase()
        ->caseId(107)
        ->tag('smoke', 'regression');

    expect(true)->toBeTrue();
});
```

### 3.10 Expected YAML

**`expected/pest-examples.yaml`:**

Add entry for the new test with tags:
```yaml
- title: uses tags
  signature: 107::p::tests::feature::qaseidtest
  status: passed
  testops_ids:
  - 107
  tags:
  - smoke
  - regression
  relations:
    suite:
      data:
      - title: P
      - title: Tests
      - title: Feature
      - title: QaseIdTest
```

Update `run.stats.passed: 50 → 51`, `run.stats.total: 52 → 53`.

### 3.11 Unit Tests

**New file:** `tests/Unit/TagsTest.php`

Tests:
- `testParseTagsFromAttribute` — Tags attribute extraction
- `testMergeClassAndMethodTags` — accumulation
- `testEmptyTags` — no tags
- `testNullReporterTag` — NullQaseReporter.tag() returns self

### 3.12 Documentation

**New file:** `docs/usage.md`

Create full usage guide following phpunit/codeception patterns, including Tags section. Structure:
- Annotations (QaseId, QaseIds, Title, Suite, Field, Tags, Parameter)
- Fluent API Methods (caseId, title, suite, field, tag, parameter, comment, attach, step)
- Examples (including tags examples)
- Configuration
- Running Tests

### 3.13 Version

`composer.json` version: `1.0.2` → `1.0.3`
`composer.json` dependency: `qase/php-commons` → `^2.1.18`

---

## 4. qase-codeception

### 4.1 New Attribute Files

Same pattern under `Qase\Codeception\Attributes` namespace:

- `src/Attributes/TagsAttributeInterface.php`
- `src/Attributes/Tags.php`

### 4.2 Metadata Model

**File:** `src/Models/Metadata.php`

Add: `public array $tags = [];`

### 4.3 AttributeParser

**File:** `src/Attributes/AttributeParser.php`

Same addition: handle `TagsAttributeInterface`.

### 4.4 Reporter

**File:** `src/Reporter.php`

In `startTest()`, after setting fields, add:

```php
$result->tags = $metadata->tags;
```

### 4.5 Example Updates

**`examples/tests/Unit/AttributeTest.php`:**
- Add `#[Tags("smoke")]` to the class level
- Add `#[Tags("regression")]` to `testWithFields` method
- Add import: `use Qase\Codeception\Attributes\Tags;`

**`examples/tests/Unit/CombinedTest.php`:**
- Add `#[Tags("smoke")]` to `testFullMetadata`

### 4.6 Expected YAML

**`expected/codeception-examples.yaml`:**

For AttributeTest tests (class-level "smoke"):
- Add `tags:\n  - smoke` to all tests from AttributeTest

For `testWithFields` (class + method):
- `tags:\n  - smoke\n  - regression`

For `testFullMetadata` in CombinedTest:
- `tags:\n  - smoke`

### 4.7 Unit Tests

**New file:** `tests/TagsAttributeTest.php`

Tests:
- `testParseTagsFromSingleAttribute`
- `testMergeClassAndMethodTags`
- `testEmptyTags`
- `testAllAttributesTogether`

### 4.8 Documentation

**`docs/usage.md`:**

1. Add Tags section after Field section
2. Update Combined Usage example with Tags
3. Update Complete Test Class Example with Tags

### 4.9 Version

`composer.json` version: `2.1.4` → `2.1.5`
`composer.json` dependency: `qase/php-commons` → `^2.1.18`

---

## Expected YAML: Tags Format

The `tags` field in expected YAML files uses a list format for validation:

```yaml
tags:
  - smoke
  - regression
```

The reporters-validator will check that tags are present. The actual API serialization (comma-separated string) is handled by ResultSpecSerializer.

---

## Files Changed Summary

### qase-php-commons (this repo)
| File | Change |
|------|--------|
| `src/Models/Result.php` | Add `tags` property |
| `src/Reporters/ResultSpecSerializer.php` | Add tags serialization + fields fallback |
| `tests/ResultSpecSerializerTest.php` | Add tags tests |
| `composer.json` | Bump version to 2.1.18 |

### qase-phpunit
| File | Change |
|------|--------|
| `src/Attributes/Tags.php` | **New** — Tags attribute |
| `src/Attributes/TagsAttributeInterface.php` | **New** — Interface |
| `src/Models/Metadata.php` | Add `tags` property |
| `src/Attributes/AttributeParser.php` | Handle TagsAttributeInterface |
| `src/QaseReporter.php` | Set tags on Result |
| `example/AttributeTest.php` | Add Tags examples |
| `expected/phpunit-examples.yaml` | Add tags to expected results |
| `tests/AttributeParserTest.php` | **New** — Parser tests |
| `docs/usage.md` | Add Tags documentation |
| `composer.json` | Bump version, update dependency |

### qase-pest
| File | Change |
|------|--------|
| `src/Attributes/Tags.php` | **New** — Tags attribute |
| `src/Attributes/TagsAttributeInterface.php` | **New** — Interface |
| `src/Models/Metadata.php` | Add `tags` property |
| `src/Attributes/AttributeParser.php` | Handle TagsAttributeInterface |
| `src/QaseReporter.php` | Set tags on Result + fluent `tag()` method |
| `src/Qase.php` | Add `Qase::tag()` static method |
| `src/NullQaseReporter.php` | Add no-op `tag()` |
| `example/tests/Feature/QaseIdTest.php` | Add tags example |
| `expected/pest-examples.yaml` | Add tags to expected results |
| `tests/Unit/TagsTest.php` | **New** — Tags tests |
| `docs/usage.md` | **New** — Full usage guide |
| `composer.json` | Bump version, update dependency |

### qase-codeception
| File | Change |
|------|--------|
| `src/Attributes/Tags.php` | **New** — Tags attribute |
| `src/Attributes/TagsAttributeInterface.php` | **New** — Interface |
| `src/Models/Metadata.php` | Add `tags` property |
| `src/Attributes/AttributeParser.php` | Handle TagsAttributeInterface |
| `src/Reporter.php` | Set tags on Result |
| `examples/tests/Unit/AttributeTest.php` | Add Tags examples |
| `examples/tests/Unit/CombinedTest.php` | Add Tags to combined test |
| `expected/codeception-examples.yaml` | Add tags to expected results |
| `tests/TagsAttributeTest.php` | **New** — Tags tests |
| `docs/usage.md` | Add Tags documentation |
| `composer.json` | Bump version, update dependency |
