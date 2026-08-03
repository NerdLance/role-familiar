<?php

namespace App\Enums;

enum ProgressionEventType: string
{
    case FamiliarSummoned = 'familiar_summoned';
    case TargetRoleAssigned = 'target_role_assigned';
    case QuestCompleted = 'quest_completed';
    case FamiliarEvolved = 'familiar_evolved';
}
