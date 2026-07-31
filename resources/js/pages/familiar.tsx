import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import CompletePrototypeQuestController from '@/actions/App/Http/Controllers/CompletePrototypeQuestController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FamiliarOverlay } from '@/features/familiars/components/familiar-overlay';
import { SpriteSheetAnimation } from '@/features/familiars/components/sprite-sheet-animation';
import { familiar as familiarRoute } from '@/routes';
import type { FamiliarManifest } from '@/types/familiars';
import type {
    PersistentFamiliarStage,
    PrototypeQuestKey,
} from '@/types/progression';

type OverlayState = 'thinking' | 'listening' | null;
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
    const [overlay, setOverlay] = useState<OverlayState>(null);
    const [celebrating, setCelebrating] = useState(false);
    const [evolution, setEvolution] = useState<EvolutionKey>(null);
    const [reducedMotion, setReducedMotion] = useState(false);
    const [liveMessage, setLiveMessage] = useState('');
    const previousStage = useRef(familiar.stage);
    const celebrationTimeout = useRef<number | undefined>(undefined);

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
                                {overlay && (
                                    <FamiliarOverlay
                                        overlay={manifest.overlays[overlay]}
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

                            <div className="mt-4 flex flex-wrap gap-2">
                                <Button
                                    onClick={() =>
                                        setOverlay(
                                            overlay === 'thinking'
                                                ? null
                                                : 'thinking',
                                        )
                                    }
                                    variant="outline"
                                >
                                    Thinking
                                </Button>
                                <Button
                                    onClick={() =>
                                        setOverlay(
                                            overlay === 'listening'
                                                ? null
                                                : 'listening',
                                        )
                                    }
                                    variant="outline"
                                >
                                    Listening
                                </Button>
                            </div>
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
