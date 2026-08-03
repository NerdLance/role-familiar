<?php

namespace App\Ai\Exceptions;

use RuntimeException;

class PrototypeInterviewGuideException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        /** @var array<string, bool|int|string> */
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct('Prototype interview guide failed.', previous: $previous);
    }
}
