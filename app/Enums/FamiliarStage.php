<?php

namespace App\Enums;

enum FamiliarStage: string
{
    case Summoned = 'summoned';
    case Attuned = 'attuned';
    case InterviewReady = 'interview_ready';
}
