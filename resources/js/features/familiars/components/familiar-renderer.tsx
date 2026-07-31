import type { FamiliarManifest } from '@/types/familiars';
import type { FamiliarMachineState } from '../familiar-machine';
import { FamiliarOverlay } from './familiar-overlay';
import { SpriteSheetAnimation } from './sprite-sheet-animation';

interface FamiliarRendererProps {
    manifest: FamiliarManifest;
    machine: FamiliarMachineState;
    reducedMotion: boolean;
    onEvolutionComplete: () => void;
}

export function FamiliarRenderer({
    manifest,
    machine,
    reducedMotion,
    onEvolutionComplete,
}: FamiliarRendererProps) {
    const animation = machine.evolutionKey
        ? manifest.evolutions[machine.evolutionKey].animation
        : manifest.stages[machine.stage].idle;
    const overlay =
        machine.visualState === 'listening' ||
        machine.visualState === 'thinking'
            ? manifest.overlays[machine.visualState]
            : null;

    return (
        <div
            className="relative mx-auto w-full max-w-[26rem]"
            aria-live="polite"
        >
            <SpriteSheetAnimation
                animation={animation}
                isPlaying={
                    !machine.paused && machine.visualState !== 'celebrating'
                }
                playbackRate={machine.playbackRate}
                reducedMotion={reducedMotion}
                onComplete={
                    machine.evolutionKey ? onEvolutionComplete : undefined
                }
            />
            {overlay && (
                <FamiliarOverlay
                    overlay={overlay}
                    isPlaying={!machine.paused}
                    playbackRate={machine.playbackRate}
                    reducedMotion={reducedMotion}
                />
            )}
            {machine.visualState === 'celebrating' && (
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0"
                >
                    <span className="absolute top-[16%] left-[18%] text-2xl motion-safe:animate-ping">
                        ✦
                    </span>
                    <span className="absolute top-[24%] right-[18%] text-xl [animation-delay:250ms] motion-safe:animate-ping">
                        ✦
                    </span>
                    <span className="absolute right-[26%] bottom-[20%] text-lg [animation-delay:500ms] motion-safe:animate-ping">
                        ✦
                    </span>
                </div>
            )}
        </div>
    );
}
