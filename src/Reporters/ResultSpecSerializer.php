<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Reporters;

use Qase\PhpCommons\Models\Attachment;
use Qase\PhpCommons\Models\Relation;
use Qase\PhpCommons\Models\Result;
use Qase\PhpCommons\Models\Step;
use Qase\PhpCommons\Models\SuiteData;

/**
 * Serializes Result model to array matching qase-tms/specs report/schemas/result.yaml.
 */
class ResultSpecSerializer
{
    public function toSpecArray(Result $result): array
    {
        // Handle tags: merge from result.tags and fields['tags'] fallback
        $tags = $result->tags;
        $fields = $result->fields;

        if (isset($fields['tags']) && is_string($fields['tags'])) {
            $fieldTags = array_map('trim', explode(',', $fields['tags']));
            $fieldTags = array_filter($fieldTags, fn($t) => $t !== '');
            $tags = array_merge($tags, $fieldTags);
            unset($fields['tags']);
        }

        $uniqueTags = array_values(array_unique($tags));

        $data = [
            'id' => $result->id,
            'title' => $result->title,
            'message' => $result->message ?: null,
            'muted' => $result->muted,
            'signature' => $result->signature,
            'fields' => $fields === [] ? new \stdClass() : $fields,
            'tags' => $uniqueTags === [] ? null : implode(',', $uniqueTags),
            'params' => $result->params === [] ? new \stdClass() : $this->normalizeParams($result->params),
            'param_groups' => $this->normalizeParamGroups($result->groupParams),
            'testops_id' => $this->firstTestOpsId($result->testOpsIds),
            'testops_ids' => $result->testOpsIds,
            'execution' => $this->executionToSpec($result->execution),
            'attachments' => array_map(
                fn($a) => $this->attachmentToSpec($a),
                $result->attachments
            ),
            'steps' => array_map(
                fn($s) => $this->stepToSpec($s),
                $result->steps
            ),
            'relations' => $this->relationsToSpec($result->relations),
        ];

        return $data;
    }

    private function normalizeParams(array $params): array
    {
        $result = [];
        foreach ($params as $key => $item) {
            if (is_string($item) || is_numeric($item) || is_bool($item)) {
                $value = (string)$item;
                $result[$key] = ($value === '') ? 'empty' : $value;
            } elseif (is_array($item)) {
                $result[$key] = ($item['value'] ?? null) === null || ($item['value'] ?? null) === ''
                    ? 'empty'
                    : (string)$item['value'];
            } else {
                $result[$key] = 'empty';
            }
        }
        return $result;
    }

    private function normalizeParamGroups(array $paramGroups): array
    {
        return array_map(function (array $group) {
            return array_map(fn($v) => ($v === null || $v === '') ? 'empty' : $v, $group);
        }, $paramGroups);
    }

    private function firstTestOpsId(?array $ids): ?int
    {
        if ($ids === null || $ids === []) {
            return null;
        }
        $first = $ids[0];
        return is_int($first) ? $first : (int) $first;
    }

    private function executionToSpec($execution): array
    {
        return [
            'status' => $execution->status,
            'start_time' => $execution->startTime,
            'end_time' => $execution->endTime,
            'duration' => $execution->duration,
            'stacktrace' => $execution->stackTrace,
            'thread' => $execution->thread,
        ];
    }

    private function attachmentToSpec(mixed $attachment): array
    {
        if ($attachment instanceof Attachment) {
            return [
                'content' => $attachment->content !== null ? base64_encode($attachment->content) : null,
                'file_name' => $attachment->title,
                'file_path' => $attachment->path,
                'mime_type' => $attachment->mime,
                'size' => null,
                'id' => $attachment->id,
            ];
        }
        if (is_array($attachment)) {
            return [
                'content' => $attachment['content'] !== null ? base64_encode($attachment['content']) : null,
                'file_name' => $attachment['title'] ?? $attachment['file_name'] ?? null,
                'file_path' => $attachment['path'] ?? $attachment['file_path'] ?? null,
                'mime_type' => $attachment['mime'] ?? $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
                'id' => $attachment['id'] ?? null,
            ];
        }
        if (is_object($attachment)) {
            return [
                'content' => $attachment->content !== null ? base64_encode($attachment->content) : null,
                'file_name' => $attachment->title ?? $attachment->file_name ?? null,
                'file_path' => $attachment->path ?? $attachment->file_path ?? null,
                'mime_type' => $attachment->mime ?? $attachment->mime_type ?? null,
                'size' => $attachment->size ?? null,
                'id' => $attachment->id ?? null,
            ];
        }
        return [];
    }

    private function stepToSpec(Step $step): array
    {
        $data = $step->data;
        $exec = $step->execution;
        return [
            'id' => $step->id,
            'step_type' => $step->stepType,
            'data' => [
                'action' => $data->action ?? null,
                'expected_result' => $data->expectedResult ?? null,
                'input_data' => null,
                'parent_id' => $step->parentId,
            ],
            'execution' => [
                'status' => $exec->getStatus(),
                'start_time' => $exec->getStartTime(),
                'duration' => $exec->getDuration(),
                'end_time' => $exec->getEndTime(),
            ],
            'attachments' => array_map(
                fn($a) => $this->attachmentToSpec($a),
                $step->attachments
            ),
            'steps' => array_map(
                fn($s) => $this->stepToSpec($s),
                $step->steps
            ),
        ];
    }

    private function relationsToSpec(Relation $relations): ?array
    {
        if ($relations->suite === null || $relations->suite->data === []) {
            return null;
        }
        $suiteData = array_map(
            fn($d) => $this->suiteDataToSpec($d),
            $relations->suite->data
        );
        return [
            'suite' => [
                'data' => $suiteData,
            ],
        ];
    }

    private function suiteDataToSpec($item): array
    {
        if ($item instanceof SuiteData) {
            return [
                'title' => $item->title,
                'public_id' => $item->publicId ?? null,
            ];
        }
        if (is_array($item)) {
            return [
                'title' => $item['title'] ?? null,
                'public_id' => $item['publicId'] ?? $item['public_id'] ?? null,
            ];
        }
        return ['title' => null, 'public_id' => null];
    }
}
