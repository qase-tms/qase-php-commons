<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Qase\PhpCommons\Interfaces\LoggerInterface;
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

    // --- Leftover state from a process that is gone must not be reused ---

    public function testStartRunCreatesANewRunWhenEveryRecordedProcessIsGone(): void
    {
        $this->writeRawState(['runId' => 2, 'count' => 9, 'pids' => [$this->deadPid() => 9]]);

        $runId = $this->stateManager->startRun(fn (): int => 77);

        $this->assertSame(77, $runId);
        $this->assertSame(1, $this->readState()['count']);
        $this->assertSame([getmypid() => 1], $this->readState()['pids']);
    }

    public function testStartRunCreatesANewRunForLegacyStateWithoutProcesses(): void
    {
        $this->writeState(2, 9);

        $this->assertSame(77, $this->stateManager->startRun(fn (): int => 77));
    }

    public function testStartRunWarnsWhenItDiscardsLeftoverState(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('run 2'));
        $stateManager = new StateManager($this->tempFile, $logger);
        $this->writeState(2, 9);

        $stateManager->startRun(fn (): int => 77);
    }

    public function testStartRunJoinsTheRunOfAParallelWorkerThatIsStillAlive(): void
    {
        $worker = $this->startSleepingProcess();
        $this->writeRawState(['runId' => 2, 'count' => 1, 'pids' => [$worker['pid'] => 1]]);

        $runId = $this->stateManager->startRun(function (): int {
            $this->fail('A live worker owns the run, a new run must not be created');
        });

        $this->assertSame(2, $runId);
        $this->assertSame(2, $this->readState()['count']);
        $this->stopProcess($worker);
    }

    public function testStartRunFallsBackToFileAgeWhenProcessesCannotBeProbed(): void
    {
        $stateManager = $this->stateManagerWithoutProbe();

        $this->writeState(2, 9);
        $this->assertSame(2, $stateManager->startRun(fn (): int => 77), 'Fresh state is trusted');

        $this->writeState(2, 9);
        touch($this->tempFile, time() - 7 * 3600);
        $this->assertSame(77, $stateManager->startRun(fn (): int => 77), 'Old state is discarded');
    }

    public function testCompleteRunCompletesWhenTheOtherWorkersAreGone(): void
    {
        $this->writeRawState(['runId' => 2, 'count' => 2, 'pids' => [getmypid() => 1, $this->deadPid() => 1]]);

        $completed = false;
        $this->stateManager->completeRun(function () use (&$completed): void {
            $completed = true;
        });

        $this->assertTrue($completed);
        $this->assertFileDoesNotExist($this->tempFile);
    }

    public function testCompleteRunWaitsForAWorkerThatIsStillAlive(): void
    {
        $worker = $this->startSleepingProcess();
        $this->writeRawState(['runId' => 2, 'count' => 2, 'pids' => [getmypid() => 1, $worker['pid'] => 1]]);

        $this->stateManager->completeRun(function (): void {
            $this->fail('The run must stay open while a worker is alive');
        });

        $this->assertSame([$worker['pid'] => 1], $this->readState()['pids']);
        $this->assertSame(1, $this->readState()['count']);
        $this->stopProcess($worker);
    }

    public function testCompleteRunRemovesTheStateEvenWhenCompletingFails(): void
    {
        $this->writeState(100, 1);

        try {
            $this->stateManager->completeRun(function (): void {
                throw new RuntimeException('callback error');
            });
        } catch (RuntimeException $e) {
            // Expected
        }

        $this->assertFileDoesNotExist($this->tempFile, 'A failed completion must not leave the run for the next invocation');
    }

    public function testDefaultStateFileIsOutsideTheVendorDirectory(): void
    {
        $file = StateManager::defaultFilename('testops');

        $this->assertStringStartsWith(sys_get_temp_dir(), $file);
        $this->assertStringNotContainsString('vendor', $file);
    }

    public function testDefaultStateFileIsStableForAScopeAndDiffersBetweenScopes(): void
    {
        $this->assertSame(StateManager::defaultFilename('testops'), StateManager::defaultFilename('testops'));
        $this->assertNotSame(StateManager::defaultFilename('testops'), StateManager::defaultFilename('report'));
    }

    private function writeRawState(array $state): void
    {
        file_put_contents($this->tempFile, json_encode($state));
    }

    private function readState(): array
    {
        return json_decode(file_get_contents($this->tempFile), true);
    }

    private function deadPid(): int
    {
        $process = proc_open([PHP_BINARY, '-r', ''], [], $pipes);
        $pid = proc_get_status($process)['pid'];
        proc_close($process);

        return $pid;
    }

    /**
     * @return array{pid: int, process: resource}
     */
    private function startSleepingProcess(): array
    {
        $process = proc_open([PHP_BINARY, '-r', 'sleep(30);'], [], $pipes);

        return ['pid' => proc_get_status($process)['pid'], 'process' => $process];
    }

    private function stopProcess(array $worker): void
    {
        proc_terminate($worker['process']);
        proc_close($worker['process']);
    }

    private function stateManagerWithoutProbe(): StateManager
    {
        return new class ($this->tempFile) extends StateManager {
            protected function canProbeProcesses(): bool
            {
                return false;
            }
        };
    }
}
