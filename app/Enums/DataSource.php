<?php

namespace App\Enums;

enum DataSource: string
{
    case User = 'user';
    case JobListing = 'job_listing';
    case AiInferred = 'ai_inferred';
    case UserCorrected = 'user_corrected';
}
