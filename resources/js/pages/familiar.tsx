import { Head, router, useHttp } from '@inertiajs/react';
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
import type { FamiliarManifest } from '@/types/familiars';
import type {
    FamiliarReaction,
    PersistentFamiliarStage,
    PrototypeQuestKey,
} from '@/types/progression';

type EvolutionKey = 'stage-1-to-2' | 'stage-2-to-3' | null;

interface InterviewGuideResponse {
    message: string;
    reaction: FamiliarReaction;
    suggested_quest: PrototypeQuestKey | null;
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

export default function Familiar({
    familiar,
    manifest,
}: {
    familiar: Familiar;
    manifest: FamiliarManifest;
}) {
    const [celebrating, setCelebrating] = useState(false);
    const [evolution, setEvolution] = useState<EvolutionKey>(null);
    const [reducedMotion, setReducedMotion] = useState(false);
    const [liveMessage, setLiveMessage] = useState('');
    const [isTypingQuestion, setIsTypingQuestion] = useState(false);
    const [guideResponse, setGuideResponse] =
        useState<InterviewGuideResponse | null>(null);
    const [guideError, setGuideError] = useState<string | null>(null);
    const guideForm = useHttp<{ message: string }, InterviewGuideResponse>({
        message: '',
    });
    const previousStage = useRef(familiar.stage);
    const celebrationTimeout = useRef<number | undefined>(undefined);
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
        if (previousStage.current === familiar.stage) {
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
    }, [familiar.stage, reducedMotion]);

    const completedQuestKeys = new Set(
        familiar.quest_progress
            .filter((quest) => quest.completed_at !== null)
            .map((quest) => quest.quest_key),
    );
    const stageKey = stageKeys[familiar.stage];
    const activeAnimation = evolution
        ? manifest.evolutions[evolution].animation
        : manifest.stages[stageKey].idle;
    const activeOverlay = guideForm.processing
        ? 'thinking'
        : isTypingQuestion
          ? 'listening'
          : null;

    const evolutionProgress =
        familiar.stage === 'summoned'
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
        setGuideError(null);
        setIsTypingQuestion(false);
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
                    const payload = JSON.parse(response.data) as {
                        message?: string;
                    };

                    setGuideError(
                        payload.message ??
                            'Queue Wyrm is unavailable right now. Please try again soon.',
                    );
                },
            })
            .catch(() => undefined);
    }

    function noteQuestionTyping(value: string) {
        guideForm.setData('message', value);
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
                    <h1 className="text-3xl font-semibold">{familiar.name}</h1>
                    <p className="text-muted-foreground">
                        {stageLabels[familiar.stage]} ·{' '}
                        {completedQuestKeys.size}/4 prototype quests completed
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Evolution progress: {evolutionProgress}
                    </p>
                </header>

                <div className="grid gap-4 lg:grid-cols-2">
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

                    <Card>
                        <CardHeader>
                            <CardTitle>Ask your familiar</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <form className="space-y-3" onSubmit={askGuide}>
                                <Label htmlFor="interview-question">
                                    Short interview-preparation question
                                </Label>
                                <Input
                                    aria-describedby="interview-question-help"
                                    aria-invalid={Boolean(
                                        guideForm.errors.message,
                                    )}
                                    disabled={guideForm.processing}
                                    id="interview-question"
                                    maxLength={1000}
                                    onBlur={() => setIsTypingQuestion(false)}
                                    onChange={(event) =>
                                        noteQuestionTyping(event.target.value)
                                    }
                                    placeholder="How can I make a STAR answer more specific?"
                                    value={guideForm.data.message}
                                />
                                <p
                                    className="text-sm text-muted-foreground"
                                    id="interview-question-help"
                                >
                                    Queue Wyrm gives concise, general guidance.
                                </p>
                                {guideForm.errors.message && (
                                    <p className="text-sm text-destructive">
                                        {guideForm.errors.message}
                                    </p>
                                )}
                                {guideError && (
                                    <p className="text-sm text-destructive">
                                        {guideError}
                                    </p>
                                )}
                                <Button
                                    disabled={
                                        guideForm.processing ||
                                        guideForm.data.message.trim().length ===
                                            0
                                    }
                                    type="submit"
                                >
                                    {guideForm.processing
                                        ? 'Queue Wyrm is thinking…'
                                        : 'Ask Queue Wyrm'}
                                </Button>
                            </form>

                            {guideResponse && (
                                <div className="rounded-lg border bg-muted/40 p-3">
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
                                        <p className="text-sm">
                                            {guideResponse.message}
                                        </p>
                                    </div>
                                    {guideResponse.suggested_quest && (
                                        <div className="mt-3 flex flex-wrap items-center gap-2">
                                            <p className="text-xs text-muted-foreground">
                                                Suggested next step: this
                                                remains optional.
                                            </p>
                                            <Button
                                                onClick={() => {
                                                    const quest =
                                                        prototypeQuests.find(
                                                            (candidate) =>
                                                                candidate.key ===
                                                                guideResponse.suggested_quest,
                                                        );

                                                    if (quest) {
                                                        completeQuest(quest);
                                                    }
                                                }}
                                                size="sm"
                                                variant="outline"
                                            >
                                                Complete suggested quest
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

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
