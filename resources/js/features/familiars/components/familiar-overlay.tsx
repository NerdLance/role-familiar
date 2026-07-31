import type { OverlayAsset } from '@/types/familiars';
import { SpriteSheetAnimation } from './sprite-sheet-animation';

interface FamiliarOverlayProps {
    overlay: OverlayAsset;
    isPlaying: boolean;
    playbackRate: number;
    reducedMotion: boolean;
}

export function FamiliarOverlay({
    overlay,
    isPlaying,
    playbackRate,
    reducedMotion,
}: FamiliarOverlayProps) {
    const position = overlay.position ?? {
        anchor: 'top-right' as const,
        x: -2,
        y: 4,
    };
    const anchorClass = {
        'top-left': 'top-0 left-0',
        'top-center': 'top-0 left-1/2 -translate-x-1/2',
        'top-right': 'top-0 right-0',
        center: 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2',
    }[position.anchor];

    return (
        <div
            className={`pointer-events-none absolute z-10 w-[30%] ${anchorClass}`}
            style={{ marginLeft: position.x, marginTop: position.y }}
        >
            <SpriteSheetAnimation
                animation={overlay.animation}
                isPlaying={isPlaying}
                playbackRate={playbackRate}
                reducedMotion={reducedMotion}
            />
        </div>
    );
}
