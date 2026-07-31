import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FamiliarRenderer } from '@/features/familiars/components/familiar-renderer';
import { FamiliarStageControls } from '@/features/familiars/components/familiar-stage-controls';
import { FamiliarStateControls } from '@/features/familiars/components/familiar-state-controls';
import { PrototypeQuestList } from '@/features/familiars/components/prototype-quest-list';
import { useFamiliarMachine } from '@/features/familiars/hooks/use-familiar-machine';
import type {
    FamiliarLabProps,
    PrototypeQuest,
} from '@/features/familiars/types';
import { familiarLab } from '@/routes';

function useReducedMotion(): boolean {
    const [reducedMotion, setReducedMotion] = useState(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const update = () => setReducedMotion(mediaQuery.matches);

        update();
        mediaQuery.addEventListener('change', update);

        return () => mediaQuery.removeEventListener('change', update);
    }, []);

    return reducedMotion;
}

export default function FamiliarLab({
    manifest,
    stageLabels,
    quests,
}: FamiliarLabProps) {
    const reducedMotion = useReducedMotion();
    const machine = useFamiliarMachine(manifest, reducedMotion);
    const [completedQuests, setCompletedQuests] = useState<Set<string>>(
        new Set(),
    );
    const [announcement, setAnnouncement] = useState(
        'Familiar Interaction Lab ready.',
    );

    const toggleQuest = (quest: PrototypeQuest) => {
        setCompletedQuests((current) => {
            const next = new Set(current);

            if (next.has(quest.key)) {
                next.delete(quest.key);
                setAnnouncement(`${quest.label} marked incomplete.`);
            } else {
                next.add(quest.key);
                machine.celebrate();
                setAnnouncement(
                    `${quest.label} complete. Queue Wyrm is celebrating.`,
                );
            }

            return next;
        });
    };

    return (
        <>
            <Head title="Familiar Interaction Lab" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
                <header className="flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                Development prototype
                            </Badge>
                            {reducedMotion && (
                                <Badge variant="outline">Reduced motion</Badge>
                            )}
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Familiar Interaction Lab
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            A local-only visual proof for Queue Wyrm assets. No
                            progression or AI behavior is saved here.
                        </p>
                    </div>
                    <div className="rounded-lg border bg-muted/50 px-3 py-2 text-sm">
                        <span className="text-muted-foreground">Stage: </span>
                        <span className="font-medium">
                            {stageLabels[machine.stage]}
                        </span>
                    </div>
                </header>

                <div aria-live="polite" className="sr-only">
                    {announcement}
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(19rem,0.8fr)] lg:gap-6">
                    <Card className="overflow-hidden">
                        <CardHeader>
                            <CardTitle>Queue Wyrm</CardTitle>
                            <CardDescription>
                                Sprite sheets are rendered with native DOM
                                positioning and nearest-neighbor scaling.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="rounded-xl border bg-[radial-gradient(circle_at_center,hsl(var(--muted)),transparent_68%)] p-4 sm:p-8">
                                <FamiliarRenderer
                                    manifest={manifest}
                                    machine={machine}
                                    reducedMotion={reducedMotion}
                                    onEvolutionComplete={
                                        machine.finishEvolution
                                    }
                                />
                            </div>
                            <div className="grid gap-5 xl:grid-cols-2">
                                <FamiliarStageControls
                                    currentStage={machine.stage}
                                    evolving={
                                        machine.visualState === 'evolving'
                                    }
                                    stageLabels={stageLabels}
                                    onEvolve={machine.triggerEvolution}
                                    onReset={machine.reset}
                                    onSelectStage={machine.selectStage}
                                />
                                <FamiliarStateControls
                                    evolving={
                                        machine.visualState === 'evolving'
                                    }
                                    paused={machine.paused}
                                    playbackRate={machine.playbackRate}
                                    state={machine.visualState}
                                    onCelebrate={machine.celebrate}
                                    onPlaybackRateChange={
                                        machine.setPlaybackRate
                                    }
                                    onToggleOverlay={machine.toggleOverlay}
                                    onTogglePaused={machine.togglePaused}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4 lg:gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Runtime debug</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <dt className="text-muted-foreground">
                                        Visual state
                                    </dt>
                                    <dd className="font-mono">
                                        {machine.visualState}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Animation
                                    </dt>
                                    <dd className="font-mono">
                                        {machine.evolutionKey ??
                                            `idle/${machine.stage}`}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Playback
                                    </dt>
                                    <dd className="font-mono">
                                        {machine.paused
                                            ? 'paused'
                                            : `${machine.playbackRate}×`}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Motion
                                    </dt>
                                    <dd className="font-mono">
                                        {reducedMotion ? 'reduced' : 'standard'}
                                    </dd>
                                </dl>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <PrototypeQuestList
                                    completed={completedQuests}
                                    manifest={manifest}
                                    quests={quests}
                                    onToggle={toggleQuest}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

FamiliarLab.layout = {
    breadcrumbs: [
        {
            title: 'Familiar Lab',
            href: familiarLab(),
        },
    ],
};
