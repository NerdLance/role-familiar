<?php

namespace App\Enums;

enum FamiliarTopicType: string
{
    case Competency = 'competency';
    case Technology = 'technology';
    case Responsibility = 'responsibility';
    case FocusArea = 'focus_area';
    case Clarification = 'clarification';
}
