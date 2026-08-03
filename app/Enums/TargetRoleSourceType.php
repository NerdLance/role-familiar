<?php

namespace App\Enums;

enum TargetRoleSourceType: string
{
    case Manual = 'manual';
    case JobListing = 'job_listing';
}
