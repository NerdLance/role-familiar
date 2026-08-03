import { Head, Link, router, useHttp } from '@inertiajs/react';
import {
    BookOpen,
    BriefcaseBusiness,
    ChevronDown,
    CircleHelp,
    MessageSquareText,
    RefreshCw,
    Sparkles,
    Target,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AskPrototypeInterviewGuideController from '@/actions/App/Http/Controllers/AskPrototypeInterviewGuideController';
import CompletePrototypeQuestController from '@/actions/App/Http/Controllers/CompletePrototypeQuestController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FamiliarOverlay } from '@/features/familiars/components/familiar-overlay';
import { SpriteSheetAnimation } from '@/features/familiars/components/sprite-sheet-animation';
import { familiar as familiarRoute } from '@/routes';
import {
    edit as editTargetRole,
    index as targetRoles,
} from '@/routes/target-roles';
import type {
    FamiliarInteractionIntent,
    FamiliarManifest,
    FamiliarNextAction,
    FamiliarTopic,
    RoleGroundedCoachingResponse,
} from '@/types/familiars';
import type {
    PersistentFamiliarStage,
    PrototypeQuestKey,
} from '@/types/progression';

type EvolutionKey = 'stage-1-to-2' | 'stage-2-to-3' | null;
type FamiliarInteractionState =
    'idle' | 'choosing_topic' | 'submitting' | 'showing_result' | 'error';

interface FamiliarInteractionRequest {
    intent: FamiliarInteractionIntent;
    topic_id?: string;
    question?: string;
}

interface Familiar {
    public_id: string;
    name: string;
    species_key: 'queue_wyrm';
    stage: PersistentFamiliarStage;
    summoned_at: string;
    attuned_at: string | null;
    interview_ready_at: string | null;
    quest_progress: Array<{
        quest_key: PrototypeQuestKey;
        completed_at: string | null;
    }>;
}

interface TargetRole {
    public_id: string;
    title: string;
    company_name: string | null;
    status: 'confirmed';
    topics: FamiliarTopic[];
}

const stageKeys = {
    summoned: 'stage-1',
    attuned: 'stage-2',
    interview_ready: 'stage-3',
} as const;

const stageLabels: Record<PersistentFamiliarStage, string> = {
    summoned: 'Ready to prepare',
    attuned: 'Preparing',
    interview_ready: 'Interview-ready',
};

const starterActions: Array<{
    intent:
        | Exclude<
              FamiliarInteractionIntent,
              'practice_topic' | 'custom_question'
          >
        | 'practice_topic';
    label: string;
    description: string;
    icon: typeof BookOpen;
}> = [
    {
        intent: 'teach',
        label: 'Teach me something',
        description: 'Learn one useful role concept.',
        icon: BookOpen,
    },
    {
        intent: 'ask_question',
        label: 'Ask me a question',
        description: 'Try one short role-grounded question.',
        icon: CircleHelp,
    },
    {
        intent: 'interview_prep',
        label: 'Interview prep',
        description: 'Choose a focused next preparation step.',
        icon: BriefcaseBusiness,
    },
    {
        intent: 'practice_topic',
        label: 'Practice a role topic',
        description: 'Pick a confirmed topic for a bounded exercise.',
        icon: Target,
    },
];

const intentLabels: Record<FamiliarInteractionIntent, string> = {
    teach: 'Teach me something',
    ask_question: 'Ask me a question',
    interview_prep: 'Interview prep',
    practice_topic: 'Practice a role topic',
    custom_question: 'Ask your own question',
};

const topicTypeLabels: Record<FamiliarTopic['type'], string> = {
    competency: 'Competency',
    technology: 'Technology',
    responsibility: 'Responsibility',
    focus_area: 'Focus area',
    clarification: 'Clarification',
};

const prototypeQuests = [
    {
        key: 'behavioral_story',
        label: 'Practice a behavioral story',
        asset: 'scroll-1',
    },
    {
        key: 'star_response',
        label: 'Structure a STAR response',
        asset: 'scroll-2',
    },
    {
        key: 'company_research',
        label: 'Research the target company',
        asset: 'observatory-lens',
    },
    {
        key: 'technical_review',
        label: 'Review a technical concept',
        asset: 'magical-book',
    },
] as const;

function evolutionBetween(
    source: PersistentFamiliarStage,
    destination: PersistentFamiliarStage,
): EvolutionKey {
    if (source === 'summoned' && destination === 'attuned') {
        return 'stage-1-to-2';
    }

    if (source === 'attuned' && destination === 'interview_ready') {
        return 'stage-2-to-3';
    }

    return null;
}

function readErrorMessage(payload: string): string | null {
    try {
        const decoded: unknown = JSON.parse(payload);

        if (
            typeof decoded === 'object' &&
            decoded !== null &&
            'message' in decoded &&
            typeof decoded.message === 'string'
        ) {
            return decoded.message;
        }
    } catch {
        return null;
    }

    return null;
}

export default function Familiar({
    familiar,
    manifest,
    targetRole,
}: {
    familiar: Familiar | null;
    manifest: FamiliarManifest;
    targetRole: TargetRole | null;
}) {
    const [celebrating, setCelebrating] = useState(false);
    const [evolution, setEvolution] = useState<EvolutionKey>(null);
    const [reducedMotion, setReducedMotion] = useState(false);
    const [liveMessage, setLiveMessage] = useState('');
    const [isTypingQuestion, setIsTypingQuestion] = useState(false);
    const [customQuestion, setCustomQuestion] = useState('');
    const [interactionState, setInteractionState] =
        useState<FamiliarInteractionState>('idle');
    const [selectedIntent, setSelectedIntent] =
        useState<FamiliarInteractionIntent | null>(null);
    const [lastRequest, setLastRequest] =
        useState<FamiliarInteractionRequest | null>(null);
    const [guideResponse, setGuideResponse] =
        useState<RoleGroundedCoachingResponse | null>(null);
    const [guideError, setGuideError] = useState<string | null>(null);
    const [progressOpen, setProgressOpen] = useState(false);
    const guideForm = useHttp<
        FamiliarInteractionRequest,
        RoleGroundedCoachingResponse
    >({ intent: 'teach' });
    const currentStage = familiar?.stage ?? 'summoned';
    const previousStage = useRef(currentStage);
    const celebrationTimeout = useRef<number | undefined>(undefined);
    const guideSubmissionInFlight = useRef(false);
    const typingTimeout = useRef<number | undefined>(undefined);

    useEffect(() => {
        const mediaQuery = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const update = () => setReducedMotion(mediaQuery.matches);

        update();
        mediaQuery.addEventListener('change', update);

        return () => mediaQuery.removeEventListener('change', update);
    }, []);

    useEffect(
        () => () => {
            if (celebrationTimeout.current) {
                window.clearTimeout(celebrationTimeout.current);
            }

            if (typingTimeout.current) {
                window.clearTimeout(typingTimeout.current);
            }
        },
        [],
    );

    useEffect(() => {
        if (!familiar || previousStage.current === familiar.stage) {
            return;
        }

        const nextEvolution = evolutionBetween(
            previousStage.current,
            familiar.stage,
        );
        previousStage.current = familiar.stage;
        setEvolution(reducedMotion ? null : nextEvolution);
        triggerCelebration(
            `Your familiar moved to ${stageLabels[familiar.stage]}.`,
        );
    }, [familiar, reducedMotion]);

    const completedQuestKeys = new Set(
        (familiar?.quest_progress ?? [])
            .filter((quest) => quest.completed_at !== null)
            .map((quest) => quest.quest_key),
    );
    const stageKey = stageKeys[currentStage];
    const activeAnimation = evolution
        ? manifest.evolutions[evolution].animation
        : manifest.stages[stageKey].idle;
    const activeOverlay = guideForm.processing
        ? 'thinking'
        : isTypingQuestion
          ? 'listening'
          : null;
    const evolutionProgress = !familiar
        ? 'Confirm a target role to begin role-specific progression'
        : familiar.stage === 'summoned'
          ? `${completedQuestKeys.size}/2 prototype activities for Preparing`
          : familiar.stage === 'attuned'
            ? `${completedQuestKeys.size}/4 prototype activities for Interview-ready`
            : 'All prototype evolution rules complete';

    function triggerCelebration(message: string) {
        if (celebrationTimeout.current) {
            window.clearTimeout(celebrationTimeout.current);
        }

        setCelebrating(true);
        setLiveMessage(message);
        celebrationTimeout.current = window.setTimeout(
            () => setCelebrating(false),
            1200,
        );
    }

    function completeQuest(quest: (typeof prototypeQuests)[number]) {
        if (!familiar) {
            return;
        }

        router.post(
            CompletePrototypeQuestController(familiar).url,
            { quest: quest.key },
            {
                preserveScroll: true,
                onSuccess: () =>
                    triggerCelebration(`${quest.label} marked complete.`),
            },
        );
    }

    function submitInteraction(request: FamiliarInteractionRequest) {
        if (guideSubmissionInFlight.current || guideForm.processing) {
            return;
        }

        guideSubmissionInFlight.current = true;
        setLastRequest(request);
        setSelectedIntent(request.intent);
        setInteractionState('submitting');
        setGuideError(null);
        setGuideResponse(null);
        setIsTypingQuestion(false);
        guideForm.clearErrors();
        guideForm.transform(() => request);
        setLiveMessage(
            `Queue Wyrm is preparing ${intentLabels[request.intent].toLowerCase()}.`,
        );

        void guideForm
            .post(AskPrototypeInterviewGuideController().url, {
                onSuccess: (response) => {
                    setGuideResponse(response);
                    setInteractionState('showing_result');

                    if (request.intent === 'custom_question') {
                        setCustomQuestion('');
                    }

                    triggerCelebration('Queue Wyrm has something ready.');
                },
                onError: () => {
                    setInteractionState('error');
                    setLiveMessage(
                        'Please correct the highlighted field and try again.',
                    );
                },
                onHttpException: (response) => {
                    const message = readErrorMessage(response.data);
                    const safeMessage =
                        message ??
                        'Queue Wyrm is unavailable right now. Please try again soon.';

                    setGuideError(safeMessage);
                    setInteractionState('error');
                    setLiveMessage(safeMessage);
                },
                onNetworkError: () => {
                    const message =
                        'Queue Wyrm could not connect. Check your connection and try again.';

                    setGuideError(message);
                    setInteractionState('error');
                    setLiveMessage(message);
                },
                onFinish: () => {
                    guideSubmissionInFlight.current = false;
                },
            })
            .catch(() => {
                guideSubmissionInFlight.current = false;
                setInteractionState('error');
            });
    }

    function chooseStarter(intent: FamiliarInteractionIntent) {
        if (intent === 'practice_topic') {
            setSelectedIntent(intent);
            setGuideResponse(null);
            setGuideError(null);
            guideForm.clearErrors();
            setInteractionState('choosing_topic');
            setLiveMessage('Choose a confirmed role topic to practice.');

            return;
        }

        submitInteraction({ intent });
    }

    function askGuide(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const question = customQuestion.trim();

        if (question.length === 0) {
            return;
        }

        submitInteraction({ intent: 'custom_question', question });
    }

    function noteQuestionTyping(value: string) {
        setCustomQuestion(value);
        guideForm.clearErrors('question');
        setGuideError(null);
        setIsTypingQuestion(true);

        if (typingTimeout.current) {
            window.clearTimeout(typingTimeout.current);
        }

        typingTimeout.current = window.setTimeout(
            () => setIsTypingQuestion(false),
            700,
        );
    }

    function tryAnotherAction() {
        setGuideResponse(null);
        setGuideError(null);
        setSelectedIntent(null);
        setInteractionState('idle');
        guideForm.clearErrors();
        setLiveMessage('Choose another way to work with Queue Wyrm.');
    }

    function runNextAction(action: FamiliarNextAction) {
        if (action.type === 'custom_question') {
            tryAnotherAction();

            return;
        }

        if (action.type === 'practice_topic' && !action.topic_id) {
            chooseStarter('practice_topic');

            return;
        }

        submitInteraction({
            intent: action.type,
            ...(action.topic_id ? { topic_id: action.topic_id } : {}),
        });
    }

    return (
        <>
            <Head title="Your Familiar" />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 overflow-x-hidden p-4 sm:p-6">
                <h1 className="sr-only">Your Familiar</h1>

                <Card className="gap-4 py-4">
                    <CardContent className="grid min-w-0 gap-4 px-4 sm:grid-cols-[minmax(0,1fr)_9rem] sm:items-center sm:px-6">
                        <div className="min-w-0 space-y-3">
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-muted-foreground">
                                    Your familiar
                                </p>
                                <h2 className="text-2xl font-semibold tracking-tight">
                                    {familiar?.name ?? 'Queue Wyrm'}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Queue Wyrm
                                </p>
                            </div>

                            {targetRole ? (
                                <div className="min-w-0 space-y-1">
                                    <p className="text-sm text-muted-foreground">
                                        Helping you prepare for
                                    </p>
                                    <p className="font-medium break-words">
                                        {targetRole.title}
                                        {targetRole.company_name
                                            ? ` at ${targetRole.company_name}`
                                            : ''}
                                    </p>
                                    <Button
                                        asChild
                                        className="h-auto min-h-11 justify-start px-0 py-1"
                                        variant="link"
                                    >
                                        <Link href={editTargetRole(targetRole)}>
                                            Review confirmed role
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <p className="max-w-lg text-sm text-muted-foreground">
                                    Confirm a target role so Queue Wyrm can help
                                    with role-grounded learning and preparation.
                                </p>
                            )}
                        </div>

                        <div className="relative mx-auto w-28 shrink-0 sm:w-36">
                            <SpriteSheetAnimation
                                animation={activeAnimation}
                                isPlaying
                                playbackRate={1}
                                reducedMotion={reducedMotion}
                                onComplete={() => setEvolution(null)}
                            />
                            {activeOverlay && (
                                <FamiliarOverlay
                                    overlay={manifest.overlays[activeOverlay]}
                                    isPlaying
                                    playbackRate={1}
                                    reducedMotion={reducedMotion}
                                />
                            )}
                            {celebrating && (
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-0 grid place-items-center text-3xl motion-safe:animate-ping"
                                >
                                    ✦
                                </span>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {targetRole && familiar ? (
                    <>
                        <section
                            aria-labelledby="guided-starters-title"
                            className="space-y-3"
                        >
                            <div className="space-y-1">
                                <h2
                                    className="text-xl font-semibold"
                                    id="guided-starters-title"
                                >
                                    What should we work on?
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Choose one useful place to begin.
                                </p>
                            </div>
                            <div className="grid grid-cols-1 gap-3 min-[380px]:grid-cols-2">
                                {starterActions.map((action) => {
                                    const Icon = action.icon;
                                    const isPending =
                                        guideForm.processing &&
                                        selectedIntent === action.intent;

                                    return (
                                        <Button
                                            aria-pressed={
                                                selectedIntent ===
                                                    action.intent &&
                                                interactionState ===
                                                    'choosing_topic'
                                            }
                                            className="h-auto min-h-20 min-w-0 items-start justify-start p-4 text-left whitespace-normal"
                                            disabled={guideForm.processing}
                                            key={action.intent}
                                            onClick={() =>
                                                chooseStarter(action.intent)
                                            }
                                            type="button"
                                            variant="outline"
                                        >
                                            <Icon
                                                aria-hidden="true"
                                                className="mt-0.5 size-5"
                                            />
                                            <span className="min-w-0 space-y-1">
                                                <span className="block font-semibold">
                                                    {action.label}
                                                </span>
                                                <span className="block text-xs leading-5 font-normal text-muted-foreground">
                                                    {isPending
                                                        ? 'Queue Wyrm is thinking…'
                                                        : action.description}
                                                </span>
                                            </span>
                                        </Button>
                                    );
                                })}
                            </div>
                        </section>

                        <Card
                            aria-busy={guideForm.processing}
                            aria-labelledby="current-interaction-title"
                            className="gap-4"
                        >
                            <CardHeader>
                                <CardTitle id="current-interaction-title">
                                    Current interaction
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="min-w-0">
                                {interactionState === 'idle' && (
                                    <div className="flex items-start gap-3 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                        <Sparkles
                                            aria-hidden="true"
                                            className="mt-0.5 size-5 shrink-0"
                                        />
                                        <p>
                                            Choose a starter above. Each action
                                            creates one bounded result here.
                                        </p>
                                    </div>
                                )}

                                {interactionState === 'choosing_topic' && (
                                    <section
                                        aria-labelledby="topic-picker-title"
                                        className="space-y-4"
                                    >
                                        <div className="space-y-1">
                                            <h3
                                                className="font-semibold"
                                                id="topic-picker-title"
                                            >
                                                Select a confirmed role topic
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                Queue Wyrm will begin one short
                                                practice exercise.
                                            </p>
                                        </div>

                                        {targetRole.topics.length > 0 ? (
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {targetRole.topics.map(
                                                    (topic) => (
                                                        <Button
                                                            className="h-auto min-h-12 min-w-0 flex-col items-start justify-start p-3 text-left whitespace-normal"
                                                            key={topic.id}
                                                            onClick={() =>
                                                                submitInteraction(
                                                                    {
                                                                        intent: 'practice_topic',
                                                                        topic_id:
                                                                            topic.id,
                                                                    },
                                                                )
                                                            }
                                                            type="button"
                                                            variant="outline"
                                                        >
                                                            <span className="min-w-0 break-words">
                                                                {topic.label}
                                                            </span>
                                                            <span className="flex flex-wrap items-center gap-1.5">
                                                                <Badge variant="secondary">
                                                                    {
                                                                        topicTypeLabels[
                                                                            topic
                                                                                .type
                                                                        ]
                                                                    }
                                                                </Badge>
                                                                {(topic.priority ===
                                                                    'critical' ||
                                                                    topic.priority ===
                                                                        'high') && (
                                                                    <Badge variant="outline">
                                                                        High
                                                                        priority
                                                                    </Badge>
                                                                )}
                                                            </span>
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                                This confirmed role does not
                                                have selectable topics yet.
                                            </p>
                                        )}

                                        <Button
                                            className="min-h-11"
                                            onClick={tryAnotherAction}
                                            type="button"
                                            variant="ghost"
                                        >
                                            Back to starters
                                        </Button>
                                    </section>
                                )}

                                {interactionState === 'submitting' && (
                                    <div
                                        className="flex min-h-24 items-center gap-3 rounded-lg bg-muted/40 p-4"
                                        role="status"
                                    >
                                        <RefreshCw
                                            aria-hidden="true"
                                            className="size-5 shrink-0 motion-safe:animate-spin"
                                        />
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                Queue Wyrm is thinking…
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Preparing{' '}
                                                {selectedIntent
                                                    ? intentLabels[
                                                          selectedIntent
                                                      ].toLowerCase()
                                                    : 'your interaction'}
                                                .
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {interactionState === 'showing_result' &&
                                    guideResponse && (
                                        <section
                                            aria-live="polite"
                                            className="max-w-3xl space-y-4"
                                        >
                                            <div className="flex items-start gap-3">
                                                <span
                                                    aria-hidden="true"
                                                    className={
                                                        guideResponse.reaction ===
                                                        'encouraging'
                                                            ? 'motion-safe:animate-bounce'
                                                            : guideResponse.reaction ===
                                                                'challenging'
                                                              ? 'motion-safe:animate-pulse'
                                                              : ''
                                                    }
                                                >
                                                    {guideResponse.reaction ===
                                                    'encouraging'
                                                        ? '✦'
                                                        : guideResponse.reaction ===
                                                            'challenging'
                                                          ? '◆'
                                                          : guideResponse.reaction ===
                                                              'clarifying'
                                                            ? '?'
                                                            : '•'}
                                                </span>
                                                <div className="min-w-0 space-y-2">
                                                    {guideResponse.title && (
                                                        <h3 className="font-semibold">
                                                            {
                                                                guideResponse.title
                                                            }
                                                        </h3>
                                                    )}
                                                    <p className="text-sm leading-6 break-words whitespace-pre-wrap">
                                                        {guideResponse.content}
                                                    </p>
                                                </div>
                                            </div>

                                            {Boolean(
                                                guideResponse.next_actions
                                                    ?.length,
                                            ) && (
                                                <div className="flex flex-wrap gap-2 border-t pt-4">
                                                    {guideResponse.next_actions?.map(
                                                        (action) => (
                                                            <Button
                                                                className="min-h-11"
                                                                key={`${action.type}-${action.topic_id ?? 'default'}`}
                                                                onClick={() =>
                                                                    runNextAction(
                                                                        action,
                                                                    )
                                                                }
                                                                size="sm"
                                                                type="button"
                                                                variant="secondary"
                                                            >
                                                                {action.label}
                                                            </Button>
                                                        ),
                                                    )}
                                                </div>
                                            )}

                                            <Button
                                                className="min-h-11"
                                                onClick={tryAnotherAction}
                                                type="button"
                                                variant="outline"
                                            >
                                                Try another action
                                            </Button>
                                        </section>
                                    )}

                                {interactionState === 'error' && (
                                    <section
                                        className="space-y-4 rounded-lg border border-destructive/30 bg-destructive/5 p-4"
                                        role="alert"
                                    >
                                        <div className="space-y-1">
                                            <h3 className="font-semibold">
                                                Queue Wyrm could not finish that
                                                interaction
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                {guideError ??
                                                    'Review the highlighted field or try the same request again.'}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {lastRequest && (
                                                <Button
                                                    className="min-h-11"
                                                    onClick={() =>
                                                        submitInteraction(
                                                            lastRequest,
                                                        )
                                                    }
                                                    type="button"
                                                >
                                                    Retry
                                                </Button>
                                            )}
                                            <Button
                                                className="min-h-11"
                                                onClick={tryAnotherAction}
                                                type="button"
                                                variant="outline"
                                            >
                                                Try another action
                                            </Button>
                                        </div>
                                    </section>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="gap-4">
                            <CardHeader>
                                <div className="flex items-start gap-3">
                                    <MessageSquareText
                                        aria-hidden="true"
                                        className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                    />
                                    <div className="space-y-1">
                                        <CardTitle>
                                            Have something specific in mind?
                                        </CardTitle>
                                        <p className="text-sm text-muted-foreground">
                                            Ask your own role-grounded question.
                                        </p>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <form
                                    aria-busy={
                                        guideForm.processing &&
                                        selectedIntent === 'custom_question'
                                    }
                                    className="space-y-3"
                                    onSubmit={askGuide}
                                >
                                    <Label htmlFor="familiar-question">
                                        Your question
                                    </Label>
                                    <Textarea
                                        aria-describedby={
                                            guideForm.errors.question
                                                ? 'familiar-question-error'
                                                : 'familiar-question-help'
                                        }
                                        aria-invalid={Boolean(
                                            guideForm.errors.question,
                                        )}
                                        disabled={guideForm.processing}
                                        id="familiar-question"
                                        maxLength={1000}
                                        onBlur={() =>
                                            setIsTypingQuestion(false)
                                        }
                                        onChange={(event) =>
                                            noteQuestionTyping(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Ask about an answer, concept, or preparation concern…"
                                        rows={3}
                                        value={customQuestion}
                                    />
                                    <p
                                        className="text-sm text-muted-foreground"
                                        id="familiar-question-help"
                                    >
                                        Uses only your confirmed active-role
                                        context. No conversation history is
                                        saved.
                                    </p>
                                    {guideForm.errors.question && (
                                        <p
                                            className="text-sm text-destructive"
                                            id="familiar-question-error"
                                            role="alert"
                                        >
                                            {guideForm.errors.question}
                                        </p>
                                    )}
                                    <Button
                                        className="min-h-11"
                                        disabled={
                                            guideForm.processing ||
                                            customQuestion.trim().length === 0
                                        }
                                        type="submit"
                                    >
                                        {guideForm.processing &&
                                        selectedIntent === 'custom_question'
                                            ? 'Queue Wyrm is thinking…'
                                            : 'Ask your own question'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </>
                ) : (
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <div className="space-y-1">
                                <h2 className="font-semibold">
                                    Queue Wyrm needs a confirmed active role
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Guided learning and preparation begin after
                                    a target role is confirmed.
                                </p>
                            </div>
                            <Button asChild className="min-h-11">
                                <Link href={targetRoles()}>
                                    Create or confirm a target role
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {familiar && (
                    <Collapsible
                        onOpenChange={setProgressOpen}
                        open={progressOpen}
                    >
                        <Card className="gap-4">
                            <CardHeader>
                                <div className="flex flex-col gap-3 min-[420px]:flex-row min-[420px]:items-center min-[420px]:justify-between">
                                    <div className="space-y-1">
                                        <CardTitle>Progress</CardTitle>
                                        <p className="text-sm text-muted-foreground">
                                            Optional prototype activity. This is
                                            not a competency or readiness
                                            assessment.
                                        </p>
                                    </div>
                                    <CollapsibleTrigger asChild>
                                        <Button
                                            className="min-h-11 justify-between"
                                            type="button"
                                            variant="outline"
                                        >
                                            {progressOpen
                                                ? 'Hide prototype progress'
                                                : 'View prototype progress'}
                                            <ChevronDown
                                                aria-hidden="true"
                                                className={`transition-transform motion-reduce:transition-none ${progressOpen ? 'rotate-180' : ''}`}
                                            />
                                        </Button>
                                    </CollapsibleTrigger>
                                </div>
                            </CardHeader>
                            <CollapsibleContent>
                                <CardContent className="space-y-5 border-t pt-5">
                                    <div className="space-y-1 text-sm">
                                        <p className="font-medium">
                                            {stageLabels[currentStage]} ·{' '}
                                            {completedQuestKeys.size}/4
                                            prototype quests completed
                                        </p>
                                        <p className="text-muted-foreground">
                                            {evolutionProgress}
                                        </p>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {prototypeQuests.map((quest) => {
                                            const complete =
                                                completedQuestKeys.has(
                                                    quest.key,
                                                );

                                            return (
                                                <div
                                                    className="flex min-w-0 items-center gap-3 rounded-lg border p-3"
                                                    key={quest.key}
                                                >
                                                    <img
                                                        alt=""
                                                        className="size-9 shrink-0 [image-rendering:pixelated]"
                                                        src={
                                                            manifest.quests[
                                                                quest.asset
                                                            ].icon.src
                                                        }
                                                    />
                                                    <span className="min-w-0 flex-1 text-sm break-words">
                                                        {quest.label}
                                                    </span>
                                                    <Button
                                                        className="min-h-11 shrink-0"
                                                        disabled={complete}
                                                        onClick={() =>
                                                            completeQuest(quest)
                                                        }
                                                        size="sm"
                                                    >
                                                        {complete
                                                            ? 'Completed'
                                                            : 'Complete'}
                                                    </Button>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>
                )}

                <p aria-live="polite" className="sr-only">
                    {liveMessage}
                </p>
            </main>
        </>
    );
}

Familiar.layout = {
    breadcrumbs: [{ title: 'Your Familiar', href: familiarRoute() }],
};
