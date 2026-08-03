<?php

namespace App\Enums;

enum FamiliarInteractionIntent: string
{
    case Teach = 'teach';
    case AskQuestion = 'ask_question';
    case InterviewPrep = 'interview_prep';
    case PracticeTopic = 'practice_topic';
    case CustomQuestion = 'custom_question';

    public function coachingInstructions(): string
    {
        return match ($this) {
            self::Teach => <<<'INSTRUCTIONS'
Select or use one confirmed role topic and explain it concisely. Connect it to the confirmed role only when the context supports that connection. Do not assume the user's current proficiency. End with an optional, low-pressure invitation to continue.
INSTRUCTIONS,
            self::AskQuestion => <<<'INSTRUCTIONS'
Produce exactly one role-grounded interview or knowledge question. Do not claim it is an exact employer question. Keep it answerable in a short response. Do not provide the answer before the user responds.
INSTRUCTIONS,
            self::InterviewPrep => <<<'INSTRUCTIONS'
Provide a compact preparation briefing using only a few high-value focus areas from the confirmed context. Do not repeat the full role profile. Suggest one immediate preparation action without predicting readiness, hiring outcomes, or likely employer questions.
INSTRUCTIONS,
            self::PracticeTopic => <<<'INSTRUCTIONS'
Begin one bounded exercise around the selected confirmed topic. Make the next expected user action clear. Do not create a persistent session, score the user, or evaluate an answer that has not been provided.
INSTRUCTIONS,
            self::CustomQuestion => <<<'INSTRUCTIONS'
Answer the user's custom question concisely using only the confirmed role context for role-specific claims. Distinguish confirmed context from general coaching guidance and acknowledge material uncertainty.
INSTRUCTIONS,
        };
    }

    public function title(?string $topicLabel = null): string
    {
        return match ($this) {
            self::Teach => $topicLabel === null ? 'Role lesson' : 'Learn: '.$topicLabel,
            self::AskQuestion => $topicLabel === null ? 'Role question' : 'Question: '.$topicLabel,
            self::InterviewPrep => 'Interview preparation',
            self::PracticeTopic => $topicLabel === null ? 'Topic practice' : 'Practice: '.$topicLabel,
            self::CustomQuestion => 'Familiar guidance',
        };
    }

    public function reaction(): FamiliarReaction
    {
        return match ($this) {
            self::Teach => FamiliarReaction::Encouraging,
            self::AskQuestion, self::PracticeTopic => FamiliarReaction::Challenging,
            self::InterviewPrep, self::CustomQuestion => FamiliarReaction::Neutral,
        };
    }
}
