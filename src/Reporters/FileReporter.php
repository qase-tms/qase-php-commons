<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;

use InvalidArgumentException;
use JsonException;
use Qase\PhpCommons\Interfaces\InternalReporterInterface;
use Qase\PhpCommons\Interfaces\LoggerInterface;
use Qase\PhpCommons\Interfaces\StateInterface;
use Qase\PhpCommons\Models\Config\ReportConfig;
use Qase\PhpCommons\Models\FileReporter\Run;
use Qase\PhpCommons\Models\FileReporter\ShortResult;
use Qase\PhpCommons\Models\Result;
use RuntimeException;

class FileReporter implements InternalReporterInterface
{
    private array $results = [];
    private ReportConfig $config;
    private LoggerInterface $logger;
    private StateInterface $state;
    private int $startTime;
    private string $runPath;
    private string $resultDir;
    private string $attachmentDir;

    public function __construct(LoggerInterface $logger, ReportConfig $config, StateInterface $state)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->state = $state;

        $rootDir = $this->config->connection->getPath();
        $this->runPath = $rootDir . DIRECTORY_SEPARATOR . "run.json";
        $this->resultDir = $rootDir . DIRECTORY_SEPARATOR . 'results';
        $this->attachmentDir = $rootDir . DIRECTORY_SEPARATOR . 'attachments';
    }

    public function startRun(): void
    {
        $this->state->startRun(function () {
            $this->startTime = time();
            self::clearDirectory($this->config->connection->getPath());
            $this->prepare_report_folder();

            $run = new Run("Test run", $this->startTime, time());
            $this->saveJsonToFile($this->runPath, $run);

            return 1;
        });
    }

    /**
     * @throws JsonException
     */
    public function completeRun(): void
    {
        $this->sendResults();

        $this->state->completeRun(
            function () {
                $run = $this->readRunJson($this->runPath);
                $run->execution->endTime = time();
                $run->execution->countDuration();

                $this->saveJsonToFile($this->runPath, $run);

                $this->logger->info('Test run completed');
            });
    }

    public function addResult(Result $result): void
    {
        $this->results[] = $result;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setResults(array $results): void
    {
        $this->results = $results;
    }

    private function prepare_report_folder(): void
    {
        $this->checkAndCreateDirectory($this->resultDir);
        $this->checkAndCreateDirectory($this->attachmentDir);
    }

    private function checkAndCreateDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Directory $directory does not exist");
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                self::clearDirectory($filePath);
                rmdir($filePath);
            } else {
                unlink($filePath);
            }
        }
    }

    private function convertToJson(object $object): string
    {
        try {
            return json_encode($object, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error('Failed to convert object to JSON: ' . $e->getMessage());
            return '';
        }
    }

    private function saveJsonToFile(string $path, object $object): void
    {
        $json = $this->convertToJson($object);
        $fileHandle = fopen($path, 'c+');
        if ($fileHandle === false) {
            throw new InvalidArgumentException("Unable to open file: $path");
        }

        try {
            if (!flock($fileHandle, LOCK_EX)) {
                throw new RuntimeException("Unable to lock file: $path");
            }

            ftruncate($fileHandle, 0);
            fwrite($fileHandle, $json);
            fflush($fileHandle);
        } finally {
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
        }
    }

    /**
     * @throws JsonException
     */
    private function readRunJson(string $path): Run
    {
        $fileHandle = fopen($path, 'r');
        if ($fileHandle === false) {
            throw new InvalidArgumentException("Unable to open file: $path");
        }

        try {
            if (!flock($fileHandle, LOCK_SH)) {
                throw new RuntimeException("Unable to lock file: $path");
            }

            $json = stream_get_contents($fileHandle);
            if ($json === false) {
                throw new RuntimeException("Unable to read file: $path");
            }
        } finally {
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
        }

        $run = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        $runObject = new Run($run->title, $run->execution->startTime, $run->execution->endTime);
        $runObject->environment = $run->environment ?? null;
        $runObject->hostData = $run->hostData;
        $runObject->threads = $run->threads;
        $runObject->suites = $run->suites;

        $runObject->execution->duration = $run->execution->duration;
        $runObject->execution->cumulativeDuration = $run->execution->cumulativeDuration;

        $runObject->stats->total = $run->stats->total;
        $runObject->stats->passed = $run->stats->passed;
        $runObject->stats->failed = $run->stats->failed;
        $runObject->stats->skipped = $run->stats->skipped;
        $runObject->stats->broken = $run->stats->broken;
        $runObject->stats->muted = $run->stats->muted;

        foreach ($run->results as $result) {
            $shortResult = new ShortResult();
            $shortResult->id = $result->id;
            $shortResult->title = $result->title;
            $shortResult->status = $result->status;
            $shortResult->duration = $result->duration;
            $shortResult->thread = $result->thread;
            $runObject->results[] = $shortResult;
        }

        return $runObject;
    }

    /**
     * @throws JsonException
     */
    public function sendResults(): void
    {
        $run = $this->readRunJson($this->runPath);
        $run->addResults($this->results);

        $this->saveJsonToFile($this->runPath, $run);

        foreach ($this->results as $result) {
            $this->saveJsonToFile($this->resultDir . "/" . $result->id . ".json", $result);
        }
    }
}
