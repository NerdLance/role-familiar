import type { FrameSequenceAnimation } from '@/types/familiars';

interface FrameSequenceAnimationProps {
    animation: FrameSequenceAnimation;
}

export function FrameSequenceAnimation({
    animation,
}: FrameSequenceAnimationProps) {
    return (
        <img
            alt=""
            aria-hidden="true"
            className="aspect-square w-full object-contain [image-rendering:pixelated]"
            src={animation.reducedMotionFallback.src}
        />
    );
}
