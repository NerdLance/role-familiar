import { useEffect, useState } from 'react';
import { FamiliarOverlay } from '@/features/familiars/components/familiar-overlay';
import { SpriteSheetAnimation } from '@/features/familiars/components/sprite-sheet-animation';
import type { QueueWyrmRoleVisual } from '@/types';

export function QueueWyrmRoleStatus({
    visual,
    mode,
    announcement,
}: {
    visual: QueueWyrmRoleVisual;
    mode: 'idle' | 'listening' | 'thinking' | 'celebrating';
    announcement: string;
}) {
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

    const overlay =
        mode === 'listening' || mode === 'thinking'
            ? visual.overlays[mode]
            : null;

    return (
        <aside
            className="min-w-0 rounded-xl border bg-card p-4"
            aria-label="Queue Wyrm status"
        >
            <div className="relative mx-auto w-full max-w-52">
                <SpriteSheetAnimation
                    animation={visual.idle}
                    isPlaying={mode !== 'celebrating'}
                    playbackRate={1}
                    reducedMotion={reducedMotion}
                />
                {overlay && (
                    <FamiliarOverlay
                        overlay={overlay}
                        isPlaying
                        playbackRate={1}
                        reducedMotion={reducedMotion}
                    />
                )}
                {mode === 'celebrating' && !reducedMotion && (
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0"
                    >
                        <span className="absolute top-[15%] left-[18%] text-2xl motion-safe:animate-ping">
                            ✦
                        </span>
                        <span className="absolute top-[22%] right-[16%] text-xl [animation-delay:200ms] motion-safe:animate-ping">
                            ✦
                        </span>
                        <span className="absolute right-[25%] bottom-[18%] text-lg [animation-delay:400ms] motion-safe:animate-ping">
                            ✦
                        </span>
                    </div>
                )}
            </div>
            <p className="text-center text-sm font-medium">{visual.name}</p>
            <p
                className="mt-1 text-center text-sm text-muted-foreground"
                aria-live="polite"
                aria-atomic="true"
            >
                {announcement}
            </p>
        </aside>
    );
}
