import type { CSSProperties } from 'react';
import type { SpriteSheetAnimation } from '@/types/familiars';
import { useFrameAnimation } from '../hooks/use-frame-animation';

interface SpriteSheetAnimationProps {
    animation: SpriteSheetAnimation;
    isPlaying: boolean;
    playbackRate: number;
    reducedMotion: boolean;
    onComplete?: () => void;
    className?: string;
}

export function SpriteSheetAnimation({
    animation,
    isPlaying,
    playbackRate,
    reducedMotion,
    onComplete,
    className,
}: SpriteSheetAnimationProps) {
    const { frame, status } = useFrameAnimation({
        animation,
        isPlaying,
        playbackRate,
        reducedMotion,
        onComplete,
    });
    const column = frame % animation.columns;
    const row = Math.floor(frame / animation.columns);
    const imageStyle = {
        transform: `translate(${-column * (100 / animation.columns)}%, ${-row * (100 / animation.rows)}%)`,
    } satisfies CSSProperties;

    if (status === 'error') {
        return (
            <div className="flex aspect-square w-full items-center justify-center rounded-md border border-dashed bg-muted p-3 text-center text-xs text-muted-foreground">
                Familiar artwork could not load.
            </div>
        );
    }

    return (
        <div
            aria-label={
                status === 'loading' ? 'Loading familiar artwork' : undefined
            }
            className={`relative aspect-square w-full overflow-hidden ${className ?? ''}`}
        >
            <img
                alt=""
                aria-hidden="true"
                className="absolute h-[300%] w-[300%] max-w-none object-contain [image-rendering:pixelated]"
                draggable={false}
                src={animation.src}
                style={imageStyle}
            />
        </div>
    );
}
