import { Head, Link, router, useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AskPrototypeInterviewGuideController from '@/actions/App/Http/Controllers/AskPrototypeInterviewGuideController';
import CompletePrototypeQuestController from '@/actions/App/Http/Controllers/CompletePrototypeQuestController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FamiliarOverlay } from '@/features/familiars/components/familiar-overlay';
import { SpriteSheetAnimation } from '@/features/familiars/components/sprite-sheet-animation';
import { familiar as familiarRoute } from '@/routes';
import {
    edit as editTargetRole,
    index as targetRoles,
} from '@/routes/target-roles';
import type {
    FamiliarManifest,
    RoleGroundedCoachingResponse,
} from '@/types/familiars';
import type {
    PersistentFamiliarStage,
    PrototypeQuestKey,
} from '@/types/progression';

type EvolutionKey = 'stage-1-to-2' | 'stage-2-to-3' | null;

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

interface ConfirmedRoleContext {
    role_title: string;
    company: string | null;
    seniority: string | null;
    summary: string | null;
    responsibilities: Array<{ content: string; priority: string }>;
    competencies: Array<{
        name: string;
        category: string;
        priority: string;
        user_confidence: number | null;
    }>;
    technologies: Array<{ name: string; priority: string }>;
    interview_focus_areas: Array<{ name: string; reason: string }>;
    unresolved_ambiguities: Array<{ question: string; reason: string }>;
}

const stageKeys = {
    summoned: 'stage-1',
    attuned: 'stage-2',
    interview_ready: 'stage-3',
} as const;

const stageLabels: Record<PersistentFamiliarStage, string> = {
    summoned: 'Summoned',
    attuned: 'Attuned',
    interview_ready: 'Interview-ready',
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

function RoleList({ title, items }: { title: string; items: string[] }) {
    return (
        <section className="min-w-0 space-y-2">
            <h2 className="text-sm font-semibold">{title}</h2>
            {items.length ? (
                <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                    {items.map((item) => (
                        <li className="break-words" key={item}>
                            {item}
                        </li>
                    ))}
                </ol>
            ) : (
                <p className="text-sm text-muted-foreground">None recorded.</p>
            )}
        </section>
    );
}

export default function Familiar({
    familiar,
    manifest,
    targetRole,
}: {
    familiar: Familiar | null;
    manifest: FamiliarManifest;
    targetRole: {
        public_id: string;
        title: string;
        company_name: string | null;
        interview_at: string | null;
        status: 'confirmed';
        context: ConfirmedRoleContext;
        acknowledgement: string;
    } | null;
}) {
    const [celebrating, setCelebrating] = useState(false);
    const [evolution, setEvolution] = useState<EvolutionKey>(null);
    const [reducedMotion, setReducedMotion] = useState(false);
    const [liveMessage, setLiveMessage] = useState('');
    const [isTypingQuestion, setIsTypingQuestion] = useState(false);
    const [guideResponse, setGuideResponse] =
        useState<RoleGroundedCoachingResponse | null>(null);
    const [guideError, setGuideError] = useState<string | null>(null);
    const guideForm = useHttp<
        { message: string },
        RoleGroundedCoachingResponse
    >({ message: '' });
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
            `Your familiar evolved to ${stageLabels[familiar.stage]}.`,
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
          ? `${completedQuestKeys.size}/2 quests needed for Attuned`
          : familiar.stage === 'attuned'
            ? `${completedQuestKeys.size}/4 quests needed for Interview-ready`
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

    function askGuide(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (guideSubmissionInFlight.current || guideForm.processing) {
            return;
        }

        guideSubmissionInFlight.current = true;
        setGuideError(null);
        setGuideResponse(null);
        setIsTypingQuestion(false);
        setLiveMessage('Queue Wyrm is thinking about your question.');
        void guideForm
            .post(AskPrototypeInterviewGuideController().url, {
                onSuccess: (response) => {
                    setGuideResponse(response);
                    guideForm.reset();
                    triggerCelebration(
                        'Queue Wyrm has answered your question.',
                    );
                },
                onError: () =>
                    setLiveMessage(
                        'Please correct the question and try again.',
                    ),
                onHttpException: (response) => {
                    const message = readErrorMessage(response.data);

                    setGuideError(
                        message ??
                            'Queue Wyrm is unavailable right now. Please try again soon.',
                    );
                    setLiveMessage(
                        message ??
                            'Queue Wyrm is unavailable right now. Please try again soon.',
                    );
                },
                onNetworkError: () => {
                    const message =
                        'Queue Wyrm could not connect. Check your connection and try again.';

                    setGuideError(message);
                    setLiveMessage(message);
                },
                onFinish: () => {
                    guideSubmissionInFlight.current = false;
                },
            })
            .catch(() => {
                guideSubmissionInFlight.current = false;
            });
    }

    function noteQuestionTyping(value: string) {
        guideForm.setData('message', value);
        guideForm.clearErrors('message');
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

    return (
        <>
            <Head title="Your Familiar" />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4 p-4 sm:p-6">
                <header>
                    <p className="text-sm text-muted-foreground">
                        Persistent prototype · Prototype rules, not a readiness
                        model
                    </p>
                    <h1 className="text-3xl font-semibold">
                        {familiar?.name ?? 'Queue Wyrm'}
                    </h1>
                    <p className="text-muted-foreground">
                        {stageLabels[currentStage]} · {completedQuestKeys.size}
                        /4 prototype quests completed
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Evolution progress: {evolutionProgress}
                    </p>
                </header>

                {targetRole ? (
                    <Card>
                        <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0 space-y-1">
                                <p className="text-sm font-medium text-muted-foreground">
                                    Current target role · Confirmed
                                </p>
                                <CardTitle>{targetRole.title}</CardTitle>
                                {targetRole.company_name && (
                                    <p className="text-sm text-muted-foreground">
                                        {targetRole.company_name}
                                    </p>
                                )}
                                {targetRole.interview_at && (
                                    <p className="text-sm text-muted-foreground">
                                        Interview:{' '}
                                        {new Intl.DateTimeFormat(undefined, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        }).format(
                                            new Date(targetRole.interview_at),
                                        )}
                                    </p>
                                )}
                            </div>
                            <Button asChild variant="outline">
                                <Link href={editTargetRole(targetRole)}>
                                    Edit target role
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="grid min-w-0 gap-5 sm:grid-cols-2">
                            <RoleList
                                title="Top responsibilities"
                                items={targetRole.context.responsibilities
                                    .slice(0, 3)
                                    .map((item) => item.content)}
                            />
                            <RoleList
                                title="Top competencies"
                                items={[
                                    ...targetRole.context.competencies.map(
                                        (item) => item.name,
                                    ),
                                    ...targetRole.context.technologies.map(
                                        (item) => item.name,
                                    ),
                                ].slice(0, 5)}
                            />
                            <div className="space-y-2 sm:col-span-2">
                                <h2 className="text-sm font-semibold">
                                    Unresolved ambiguities
                                </h2>
                                {targetRole.context.unresolved_ambiguities
                                    .length ? (
                                    <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                        {targetRole.context.unresolved_ambiguities.map(
                                            (item) => (
                                                <li key={item.question}>
                                                    {item.question}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No unresolved clarification questions.
                                    </p>
                                )}
                            </div>
                            <p className="rounded-lg border bg-muted/40 p-3 text-sm sm:col-span-2">
                                “{targetRole.acknowledgement}”
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="space-y-2 pt-6">
                            <p className="font-medium">
                                Queue Wyrm is waiting for a confirmed role.
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Confirm a target role to create a separate Stage
                                1 Queue Wyrm and begin role-specific
                                progression.
                            </p>
                            <Button asChild>
                                <Link href={targetRoles()}>
                                    Create or confirm a target role
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {familiar && (
                        <Card>
                            <CardContent className="pt-6">
                                <div className="relative mx-auto max-w-sm">
                                    <SpriteSheetAnimation
                                        animation={activeAnimation}
                                        isPlaying
                                        playbackRate={1}
                                        reducedMotion={reducedMotion}
                                        onComplete={() => setEvolution(null)}
                                    />
                                    {activeOverlay && (
                                        <FamiliarOverlay
                                            overlay={
                                                manifest.overlays[activeOverlay]
                                            }
                                            isPlaying
                                            playbackRate={1}
                                            reducedMotion={reducedMotion}
                                        />
                                    )}
                                    {celebrating && (
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none absolute inset-0 grid place-items-center text-4xl motion-safe:animate-ping"
                                        >
                                            ✦
                                        </span>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {familiar && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Ask your familiar</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Queue Wyrm will answer using your confirmed{' '}
                                    <span className="font-medium text-foreground">
                                        {targetRole?.title}
                                        {targetRole?.company_name
                                            ? ` at ${targetRole.company_name}`
                                            : ''}
                                    </span>{' '}
                                    role.
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <form
                                    aria-busy={guideForm.processing}
                                    className="space-y-3"
                                    onSubmit={askGuide}
                                >
                                    <Label htmlFor="interview-question">
                                        Short interview-preparation question
                                    </Label>
                                    <Input
                                        aria-describedby={[
                                            'interview-question-help',
                                            guideForm.errors.message
                                                ? 'interview-question-error'
                                                : null,
                                            guideError
                                                ? 'interview-guide-error'
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' ')}
                                        aria-invalid={Boolean(
                                            guideForm.errors.message,
                                        )}
                                        disabled={guideForm.processing}
                                        id="interview-question"
                                        maxLength={1000}
                                        onBlur={() =>
                                            setIsTypingQuestion(false)
                                        }
                                        onChange={(event) =>
                                            noteQuestionTyping(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="How can I make a STAR answer more specific?"
                                        value={guideForm.data.message}
                                    />
                                    <p
                                        className="text-sm text-muted-foreground"
                                        id="interview-question-help"
                                    >
                                        Queue Wyrm gives concise, general
                                        coaching grounded in your reviewed role
                                        information.
                                    </p>
                                    {guideForm.errors.message && (
                                        <p
                                            className="text-sm text-destructive"
                                            id="interview-question-error"
                                            role="alert"
                                        >
                                            {guideForm.errors.message}
                                        </p>
                                    )}
                                    {guideError && (
                                        <p
                                            className="text-sm text-destructive"
                                            id="interview-guide-error"
                                            role="alert"
                                        >
                                            {guideError}
                                        </p>
                                    )}
                                    <Button
                                        disabled={
                                            guideForm.processing ||
                                            guideForm.data.message.trim()
                                                .length === 0
                                        }
                                        type="submit"
                                    >
                                        {guideForm.processing
                                            ? 'Queue Wyrm is thinking…'
                                            : 'Ask Queue Wyrm'}
                                    </Button>
                                </form>

                                {guideResponse && (
                                    <section
                                        aria-labelledby="coaching-answer-title"
                                        className="space-y-4 rounded-lg border bg-muted/40 p-3"
                                    >
                                        <div className="flex items-start gap-2">
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
                                            <div className="min-w-0 space-y-1">
                                                <h3
                                                    className="text-sm font-semibold"
                                                    id="coaching-answer-title"
                                                >
                                                    Coaching answer
                                                </h3>
                                                <p className="text-sm break-words">
                                                    {guideResponse.message}
                                                </p>
                                            </div>
                                        </div>
                                        {guideResponse.role_connections.length >
                                            0 && (
                                            <section className="space-y-2">
                                                <h3 className="text-sm font-semibold">
                                                    Connections to your
                                                    confirmed role
                                                </h3>
                                                <p className="text-xs text-muted-foreground">
                                                    Based on your reviewed role
                                                    information, not citations
                                                    to the original listing.
                                                </p>
                                                <ul className="space-y-2">
                                                    {guideResponse.role_connections.map(
                                                        (connection) => (
                                                            <li
                                                                className="text-sm"
                                                                key={`${connection.topic}-${connection.explanation}`}
                                                            >
                                                                <span className="font-medium">
                                                                    {
                                                                        connection.topic
                                                                    }
                                                                    :{' '}
                                                                </span>
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        connection.explanation
                                                                    }
                                                                </span>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </section>
                                        )}
                                        {guideResponse.uncertainties.length >
                                            0 && (
                                            <section className="space-y-2">
                                                <h3 className="text-sm font-semibold">
                                                    Missing or unresolved
                                                    information
                                                </h3>
                                                <ul className="space-y-2">
                                                    {guideResponse.uncertainties.map(
                                                        (uncertainty) => (
                                                            <li
                                                                className="text-sm"
                                                                key={`${uncertainty.topic}-${uncertainty.explanation}`}
                                                            >
                                                                <span className="font-medium">
                                                                    {
                                                                        uncertainty.topic
                                                                    }
                                                                    :{' '}
                                                                </span>
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        uncertainty.explanation
                                                                    }
                                                                </span>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </section>
                                        )}
                                        {guideResponse.suggested_next_action &&
                                            guideResponse.suggested_next_action
                                                .type !== 'none' && (
                                                <section className="space-y-1 border-t pt-3">
                                                    <h3 className="text-sm font-semibold">
                                                        Suggested next action
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        {
                                                            guideResponse
                                                                .suggested_next_action
                                                                .label
                                                        }
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        This is optional and
                                                        does not change quests,
                                                        progress, or your role.
                                                    </p>
                                                </section>
                                            )}
                                    </section>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Prototype quests</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {prototypeQuests.map((quest) => {
                                const complete = completedQuestKeys.has(
                                    quest.key,
                                );

                                return (
                                    <div
                                        className="flex items-center gap-3 rounded-lg border p-3"
                                        key={quest.key}
                                    >
                                        <img
                                            alt=""
                                            className="size-10 [image-rendering:pixelated]"
                                            src={
                                                manifest.quests[quest.asset]
                                                    .icon.src
                                            }
                                        />
                                        <span className="flex-1 text-sm">
                                            {quest.label}
                                        </span>
                                        <Button
                                            disabled={complete}
                                            onClick={() => completeQuest(quest)}
                                            size="sm"
                                        >
                                            {complete
                                                ? 'Completed'
                                                : 'Complete quest'}
                                        </Button>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </div>

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
