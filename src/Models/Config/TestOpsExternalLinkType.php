<?php

declare(strict_types=1);

namespace Qase\PhpCommons\Models\Config;

class TestOpsExternalLinkType
{
    public string $type;
    public string $link;

    public function __construct(string $type, string $link)
    {
        if (!ExternalLinkType::isValid($type)) {
            throw new \InvalidArgumentException("Invalid external link type: {$type}");
        }

        $this->type = $type;
        $this->link = $link;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        if (!ExternalLinkType::isValid($type)) {
            throw new \InvalidArgumentException("Invalid external link type: {$type}");
        }
        $this->type = $type;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }
}
