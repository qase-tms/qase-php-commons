<?php
declare(strict_types=1);

namespace Qase\PhpCommons\Utils;

use Exception;
use Qase\PhpCommons\Interfaces\StateInterface;

class StateManager implements StateInterface
{
    private string $filename;

    public function __construct()
    {
        $this->filename = __DIR__ . '/data.json';
        if (!file_exists($this->filename)) {
            file_put_contents($this->filename, json_encode(["runId" => null, "count" => 0], JSON_PRETTY_PRINT));
        }
    }

    /**
     * @throws Exception
     */
    public function startRun(callable $createRun): int
    {
        $file = fopen($this->filename, 'c+');
        if ($file === false) {
            throw new Exception("Cannot open file.");
        }

        if (!flock($file, LOCK_EX)) {
            fclose($file);
            throw new Exception("Can not lock file.");
        }

        try {
            $fileContents = stream_get_contents($file);
            $data = json_decode($fileContents, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $data = ["runId" => null, "count" => 0];
            }

            if (empty($data['runId'])) {
                $data['runId'] = $createRun();
                $data['count'] = 1;
            } else {
                $data['count'] += 1;
            }

            $runId = $data['runId'];

            ftruncate($file, 0);
            rewind($file);
            fwrite($file, json_encode($data, JSON_PRETTY_PRINT));
            fflush($file);

            return $runId;
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
        $file = fopen($this->filename, 'c+');
        if ($file === false) {
            throw new Exception("Cannot open file.");
        }

        if (!flock($file, LOCK_EX)) {
            fclose($file);
            throw new Exception("Can not lock file.");
        }

        $shouldComplete = false;

        try {
            $fileContents = stream_get_contents($file);
            $data = json_decode($fileContents, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $data = ["runId" => null, "count" => 0];
            }

            if (!empty($data['runId']) && $data['count'] > 0) {
                $data['count'] -= 1;
                if ($data['count'] === 0) {
                    $shouldComplete = true;
                }
            }

            if (!$shouldComplete) {
                ftruncate($file, 0);
                rewind($file);
                fwrite($file, json_encode($data, JSON_PRETTY_PRINT));
                fflush($file);
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }

        if ($shouldComplete) {
            $completeRun();
            if (file_exists($this->filename)) {
                unlink($this->filename);
            }
        }
    }
}
