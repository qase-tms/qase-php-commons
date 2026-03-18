<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Utils\StateManager;
use RuntimeException;

class StateManagerTest extends TestCase
{
    private string $tempFile;
    private StateManager $stateManager;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/state_manager_test_' . uniqid() . '.json';
        $this->stateManager = new StateManager();
        $reflection = new \ReflectionClass($this->stateManager);
        $prop = $reflection->getProperty('filename');
        $prop->setAccessible(true);
        $prop->setValue($this->stateManager, $this->tempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        // Also clean up the default data.json that constructor creates
        $defaultFile = dirname((new \ReflectionClass(StateManager::class))->getFileName()) . '/data.json';
        if (file_exists($defaultFile)) {
            unlink($defaultFile);
        }
    }

    /**
     * Check if a file can be exclusively locked (non-blocking).
     * Returns true if the file is not locked by another handle.
     */
    private function canAcquireLock(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return true; // File was deleted, so no lock
        }
        $fh = fopen($filePath, 'c+');
        if ($fh === false) {
            return false;
        }
        $locked = flock($fh, LOCK_EX | LOCK_NB);
        if ($locked) {
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return $locked;
    }

    /**
     * Write a known state to the temp file.
     */
    private function writeState(int $runId, int $count): void
    {
        file_put_contents($this->tempFile, json_encode(['runId' => $runId, 'count' => $count], JSON_PRETTY_PRINT));
    }

    // --- Baseline functionality tests ---

    public function testStartRunReturnsRunIdFromCallback(): void
    {
        $runId = $this->stateManager->startRun(function (): int {
            return 42;
        });

        $this->assertSame(42, $runId);
    }

    public function testCompleteRunCallsCallbackWhenCountReachesZero(): void
    {
        // Set up state: runId=100, count=1 (one process active)
        $this->writeState(100, 1);

        $callbackCalled = false;
        $this->stateManager->completeRun(function () use (&$callbackCalled): void {
            $callbackCalled = true;
        });

        $this->assertTrue($callbackCalled, 'completeRun callback should be called when count reaches zero');
    }

    // --- BUG-01: flock(LOCK_UN) must be called before fclose on zero count ---

    public function testCompleteRunReleasesLockBeforeCloseOnZeroCount(): void
    {
        // Set up state: runId=100, count=1
        $this->writeState(100, 1);

        $this->stateManager->completeRun(function (): void {
            // no-op
        });

        // After completeRun, the file may have been deleted (unlink).
        // If it still exists, we must be able to acquire a lock on it.
        $this->assertTrue(
            $this->canAcquireLock($this->tempFile),
            'File lock must be released after completeRun with count reaching zero'
        );
    }

    // --- BUG-02: exception from $completeRun() callback must not leak lock/handle ---

    public function testCompleteRunCallbackExceptionReleasesLock(): void
    {
        // Set up state: runId=100, count=1
        $this->writeState(100, 1);

        try {
            $this->stateManager->completeRun(function (): void {
                throw new RuntimeException('callback error');
            });
        } catch (RuntimeException $e) {
            // Expected
        }

        // After exception, the file lock must be released
        $this->assertTrue(
            $this->canAcquireLock($this->tempFile),
            'File lock must be released even when completeRun callback throws'
        );
    }

    public function testCompleteRunCallbackExceptionReleasesFileHandle(): void
    {
        // Set up state: runId=100, count=1
        $this->writeState(100, 1);

        try {
            $this->stateManager->completeRun(function (): void {
                throw new RuntimeException('callback error');
            });
        } catch (RuntimeException $e) {
            // Expected
        }

        // After exception, the file handle must be closed.
        // Verify by deleting the file (would fail on some platforms if handle is open).
        if (file_exists($this->tempFile)) {
            $deleted = unlink($this->tempFile);
            $this->assertTrue($deleted, 'File must be deletable after completeRun callback throws (handle closed)');
        } else {
            // File was already cleaned up, which is also acceptable
            $this->assertTrue(true);
        }
    }

    // --- Exception from $createRun() callback must not leak file handle ---

    public function testStartRunExceptionFromCallbackReleasesFileHandle(): void
    {
        try {
            $this->stateManager->startRun(function (): int {
                throw new RuntimeException('createRun error');
            });
        } catch (RuntimeException $e) {
            // Expected
        }

        // After exception, the file lock must be released
        $this->assertTrue(
            $this->canAcquireLock($this->tempFile),
            'File lock must be released even when startRun callback throws'
        );
    }

    // --- REF-01: Structural tests for try-finally usage ---

    public function testStartRunUsesTryFinally(): void
    {
        $source = file_get_contents((new \ReflectionClass(StateManager::class))->getFileName());
        $this->assertStringContainsString('finally', $source, 'StateManager must use try-finally blocks');
    }

    public function testCompleteRunUsesTryFinally(): void
    {
        $source = file_get_contents((new \ReflectionClass(StateManager::class))->getFileName());
        // Count occurrences of 'finally' to ensure both methods have it
        $count = substr_count($source, 'finally');
        $this->assertGreaterThanOrEqual(2, $count, 'StateManager must use try-finally in both startRun and completeRun');
    }
}
