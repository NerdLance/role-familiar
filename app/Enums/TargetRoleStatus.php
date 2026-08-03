<?php

namespace App\Enums;

enum TargetRoleStatus: string
{
    case Draft = 'draft';
    case AnalysisPending = 'analysis_pending';
    case ReviewRequired = 'review_required';
    case Confirmed = 'confirmed';
    case AnalysisFailed = 'analysis_failed';
    case Archived = 'archived';
}
