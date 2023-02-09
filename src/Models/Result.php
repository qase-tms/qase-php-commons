<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models;
use Ramsey\Uuid\Uuid;

class Result extends BaseModel
{
    private const SUITE_TITLE_SEPARATOR = "\t";
    
    protected string $id;
    protected ?int $testOpsId = null;
    protected string $title = '';
    protected string $suite = '';
    protected string $description = '';
    protected array $steps = [];
    protected array $attachments = [];
    protected int $duration = 0;
    protected string $status = 'passed';
    protected string $comment = '';
    protected string $stacktrace = '';
    protected $params = [];
    protected int $completed_at;

    public function __construct(string $fullTestName)
    {
        $this->id = Uuid::uuid4()->__toString();
        [$namespace, $methodName] = $this->explodeFullTestName($fullTestName);
        $this->title = $this->clearPrefix($methodName, ['test']);

        $this->suite = self::SUITE_TITLE_SEPARATOR .
                str_replace('\\', self::SUITE_TITLE_SEPARATOR, $this->clearPrefix($namespace, ['Test\\', 'Tests\\']));

        $this->getDataFromAnnotation($namespace, $methodName);

        if (preg_match('/with data set "(.+)"|(#\d+)/U', $fullTestName, $paramMatches, PREG_UNMATCHED_AS_NULL) === 1) {
            $this->params = ['params' => $paramMatches[1] ?? $paramMatches[2]];
        }
    }

    private function getDataFromAnnotation(string $namespace, string $methodName): void
    {
        $reflection = new \ReflectionMethod($namespace, $methodName);

        $docComment = $reflection->getDocComment();
        
        if (!$docComment) {
            return;
        }

        if ($testOpsId = $this->getTestOpsIdFromAnnotation($docComment)) {
            $this->testOpsId = (int)$testOpsId;
        }

        if ($title = $this->getTitleFromAnnotation($docComment)) {
            $this->title = $title;
        }

        if ($description = $this->getDescriptionFromAnnotation($docComment)) {
            $this->description = $description;
        }
    }

    private function getTestOpsIdFromAnnotation(string $docComment): ?int
    {
        if (!$docComment || !preg_match_all('/@qaseId:\s*(?P<qaseId>.+)/', $docComment, $qaseIdMatches)) {
            return null;
        }

        if (!($qaseIdMatches['qaseId'][0] ?? false)) {
            return null;
        }

        return (int)$qaseIdMatches['qaseId'][0] ?: null;
    }

    private function getTitleFromAnnotation(string $docComment): string
    {
        if (!$docComment || !preg_match_all('/@qaseTitle:\s*(?P<qaseTitle>.+)/', $docComment, $qaseTitleMatches)) {
            return '';
        }

        if (!($qaseTitleMatches['qaseTitle'][0] ?? false)) {
            return '';
        }

        return (string)$qaseTitleMatches['qaseTitle'][0] ?: ''; 
    }

    private function getDescriptionFromAnnotation(string $docComment): string
    {
        if (!$docComment || !preg_match_all('/@qaseDescription:\s*(?P<qaseDescription>.+)/', $docComment, $qaseDescriptionMatches)) {
            return '';
        }

        if (!($qaseDescriptionMatches['qaseDescription'][0] ?? false)) {
            return '';
        }

        return (string)$qaseDescriptionMatches['qaseDescription'][0] ?: ''; 
    }

    private function explodeFullTestName($fullTestName): array
    {
        if (!preg_match_all('/(?P<namespace>.+)::(?P<methodName>\w+)/', $fullTestName, $testNameMatches)) {
            $this->logger->writeln("WARNING: Could not parse test name '{$fullTestName}'");
            throw new \RuntimeException('Could not parse test name');
        }

        return [$testNameMatches['namespace'][0], $testNameMatches['methodName'][0]];
    }

    /**
     * @param string $title
     * @param array[string] $prefixes
     * @return string
     */
    private function clearPrefix(string $title, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            $prefixLength = mb_strlen($prefix);
            if (strncmp($title, $prefix, $prefixLength) === 0) {
                return mb_substr($title, $prefixLength);
            }
        }

        return $title;
    }
}