<?php

namespace App\Enums;

enum CoachingNextActionType: string
{
    case AskFollowUp = 'ask_follow_up';
    case PracticeQuestion = 'practice_question';
    case ExplainTopic = 'explain_topic';
    case ReviewAnswer = 'review_answer';
    case None = 'none';
}
