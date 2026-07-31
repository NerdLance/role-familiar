import { Button } from '@/components/ui/button';
import type { FamiliarStageKey } from '@/types/familiars';

interface FamiliarStageControlsProps {
    currentStage: FamiliarStageKey;
    stageLabels: Record<FamiliarStageKey, string>;
    evolving: boolean;
    onSelectStage: (stage: FamiliarStageKey) => void;
    onEvolve: (transition: 'stage-1-to-2' | 'stage-2-to-3') => void;
    onReset: () => void;
}

export function FamiliarStageControls({
    currentStage,
    stageLabels,
    evolving,
    onSelectStage,
    onEvolve,
    onReset,
}: FamiliarStageControlsProps) {
    return (
        <section className="space-y-3" aria-labelledby="stage-controls-heading">
            <h2 id="stage-controls-heading" className="text-sm font-semibold">
                Stage controls
            </h2>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                {(Object.keys(stageLabels) as FamiliarStageKey[]).map(
                    (stage) => (
                        <Button
                            key={stage}
                            variant={
                                currentStage === stage ? 'default' : 'outline'
                            }
                            disabled={evolving}
                            onClick={() => onSelectStage(stage)}
                        >
                            {stageLabels[stage]}
                        </Button>
                    ),
                )}
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    disabled={evolving || currentStage !== 'stage-1'}
                    onClick={() => onEvolve('stage-1-to-2')}
                >
                    Evolve to Attuned
                </Button>
                <Button
                    disabled={evolving || currentStage !== 'stage-2'}
                    onClick={() => onEvolve('stage-2-to-3')}
                >
                    Evolve to Interview-ready
                </Button>
                <Button variant="outline" disabled={evolving} onClick={onReset}>
                    Reset to Summoned
                </Button>
            </div>
        </section>
    );
}
