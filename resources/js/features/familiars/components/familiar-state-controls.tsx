import { Button } from '@/components/ui/button';

interface FamiliarStateControlsProps {
    state: string;
    paused: boolean;
    playbackRate: number;
    evolving: boolean;
    onToggleOverlay: (overlay: 'listening' | 'thinking') => void;
    onCelebrate: () => void;
    onTogglePaused: () => void;
    onPlaybackRateChange: (rate: number) => void;
}

export function FamiliarStateControls({
    state,
    paused,
    playbackRate,
    evolving,
    onToggleOverlay,
    onCelebrate,
    onTogglePaused,
    onPlaybackRateChange,
}: FamiliarStateControlsProps) {
    return (
        <section className="space-y-3" aria-labelledby="state-controls-heading">
            <h2 id="state-controls-heading" className="text-sm font-semibold">
                State controls
            </h2>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant={state === 'listening' ? 'default' : 'outline'}
                    disabled={evolving}
                    onClick={() => onToggleOverlay('listening')}
                >
                    Toggle listening
                </Button>
                <Button
                    variant={state === 'thinking' ? 'default' : 'outline'}
                    disabled={evolving}
                    onClick={() => onToggleOverlay('thinking')}
                >
                    Toggle thinking
                </Button>
                <Button
                    variant="outline"
                    disabled={evolving}
                    onClick={onCelebrate}
                >
                    Celebrate
                </Button>
                <Button
                    variant="outline"
                    disabled={evolving}
                    onClick={onTogglePaused}
                >
                    {paused ? 'Resume' : 'Pause'}
                </Button>
            </div>
            <label className="flex max-w-sm flex-col gap-2 text-sm font-medium">
                Playback speed: {playbackRate.toFixed(2)}×
                <input
                    aria-label="Playback speed"
                    className="accent-primary"
                    disabled={evolving}
                    max="2"
                    min="0.5"
                    step="0.25"
                    type="range"
                    value={playbackRate}
                    onChange={(event) =>
                        onPlaybackRateChange(Number(event.target.value))
                    }
                />
            </label>
        </section>
    );
}
