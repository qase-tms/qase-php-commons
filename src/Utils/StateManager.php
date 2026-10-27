<?php
declare(strict_types=1);

namespace Qase\PhpCommons\Utils;

use Exception;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\StateInterface;

/**
 * Shares one test run between the processes of a single invocation (e.g. parallel workers).
 *
 * Every process that joins the run records its PID, so state left behind by a crashed or
 * killed invocation can be told apart from a run that live workers are still reporting into.
 */
class StateManager implements StateInterface
{
    // Only used where PIDs cannot be probed: no worker joins a live run after this long
    private const STALE_AFTER_SECONDS = 6 * 3600;

    private string $filename;
    private ?LoggerInterface $logger;

    public function __construct(?string $filename = null, ?LoggerInterface $logger = null)
    {
        $this->filename = $filename ?? self::defaultFilename('');
        $this->logger = $logger;
    }

    /**
     * Keyed by the working directory, so parallel workers of one project share the state
     * while other projects and cached vendor directories do not.
     */
    public static function defaultFilename(string $scope): string
    {
        $key = md5((getcwd() ?: __DIR__) . '|' . $scope);

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qase-php-commons' . DIRECTORY_SEPARATOR . $key . '.json';
    }

    /**
     * @throws Exception
     */
    public function startRun(callable $createRun): int
    {
        $file = $this->open();

        try {
            $data = $this->read($file);

            if (!empty($data['runId']) && $this->isAbandoned($data)) {
                $this->logger?->warning(sprintf(
                    'Found the state of test run %d left by a finished process, starting a new run',
                    $data['runId']
                ));
                $data = self::emptyState();
            }

            if (empty($data['runId'])) {
                $data['runId'] = $createRun();
                $data['count'] = 1;
                $data['pids'] = [];
            } else {
                $data['count'] += 1;
            }

            $pid = getmypid();
            $data['pids'][$pid] = ($data['pids'][$pid] ?? 0) + 1;

            $this->write($file, $data);

            return $data['runId'];
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    /**
     * @throws Exception
     */
    public function completeRun(callable $completeRun): void
    {
        $file = $this->open();

        $shouldComplete = false;

        try {
            $data = $this->read($file);

            if (!empty($data['runId']) && $data['count'] > 0) {
                $data['count'] -= 1;

                if (isset($data['pids'])) {
                    $data['pids'] = $this->leave($data['pids'], getmypid());
                    // Workers that died never leave on their own and would keep the run open forever
                    if ($this->canProbeProcesses()) {
                        $data['count'] = array_sum($data['pids']);
                    }
                }

                if ($data['count'] === 0) {
                    $shouldComplete = true;
                }
            }

            if (!$shouldComplete) {
                $this->write($file, $data);
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }

        if ($shouldComplete) {
            try {
                $completeRun();
            } finally {
                if (file_exists($this->filename)) {
                    unlink($this->filename);
                }
            }
        }
    }

    protected function canProbeProcesses(): bool
    {
        return function_exists('posix_kill') || is_dir('/proc/self');
    }

    protected function isProcessAlive(int $pid): bool
    {
        if (function_exists('posix_kill')) {
            // EPERM: the process exists but belongs to another user
            return posix_kill($pid, 0) || posix_get_last_error() === 1;
        }

        return is_dir('/proc/' . $pid);
    }

    private function isAbandoned(array $data): bool
    {
        if (!$this->canProbeProcesses()) {
            return time() - (int)filemtime($this->filename) > self::STALE_AFTER_SECONDS;
        }

        foreach (array_keys($data['pids'] ?? []) as $pid) {
            if ($this->isProcessAlive((int)$pid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int> $pids
     * @return array<int, int>
     */
    private function leave(array $pids, int $pid): array
    {
        if (isset($pids[$pid]) && --$pids[$pid] === 0) {
            unset($pids[$pid]);
        }

        if (!$this->canProbeProcesses()) {
            return $pids;
        }

        return array_filter(
            $pids,
            fn ($otherPid): bool => $this->isProcessAlive((int)$otherPid),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return resource
     * @throws Exception
     */
    private function open()
    {
        $directory = dirname($this->filename);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $file = fopen($this->filename, 'c+');
        if ($file === false) {
            throw new Exception("Cannot open file.");
        }

        if (!flock($file, LOCK_EX)) {
            fclose($file);
            throw new Exception("Can not lock file.");
        }

        return $file;
    }

    /**
     * @param resource $file
     */
    private function read($file): array
    {
        $data = json_decode((string)stream_get_contents($file), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return self::emptyState();
        }

        return $data;
    }

    /**
     * @param resource $file
     */
    private function write($file, array $data): void
    {
        ftruncate($file, 0);
        rewind($file);
        fwrite($file, json_encode($data, JSON_PRETTY_PRINT));
        fflush($file);
    }

    private static function emptyState(): array
    {
        return ["runId" => null, "count" => 0, "pids" => []];
    }
}
