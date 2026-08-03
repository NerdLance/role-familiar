<?php

namespace App\Ai\Exceptions;

use RuntimeException;
use Throwable;

class RoleGroundedCoachingException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        /** @var array<string, bool|int|string> */
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct('Role-grounded coaching failed.', previous: $previous);
    }
}
