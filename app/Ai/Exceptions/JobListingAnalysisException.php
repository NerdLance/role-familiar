<?php

namespace App\Ai\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class JobListingAnalysisException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $category,
        public readonly bool $retryable = false,
    ) {
        parent::__construct('Job listing analysis failed.');
    }
}
