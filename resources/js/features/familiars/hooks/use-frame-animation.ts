import { useEffect, useRef, useState } from 'react';
import type { FamiliarAnimation } from '@/types/familiars';

interface UseFrameAnimationOptions {
    animation: FamiliarAnimation;
    isPlaying: boolean;
    playbackRate: number;
    reducedMotion: boolean;
    onComplete?: () => void;
}

const fallbackFramesPerSecond = 8;

export function useFrameAnimation({
    animation,
    isPlaying,
    playbackRate,
    reducedMotion,
    onComplete,
}: UseFrameAnimationOptions) {
    const [frameState, setFrameState] = useState({ key: '', value: 0 });
    const [statusState, setStatusState] = useState<{
        key: string;
        value: 'loading' | 'ready' | 'error';
    }>({ key: '', value: 'loading' });
    const [documentVisible, setDocumentVisible] = useState(
        () => typeof document === 'undefined' || !document.hidden,
    );
    const completedRef = useRef(false);
    const source =
        animation.kind === 'sprite_sheet' ? animation.src : animation.frames[0];
    const frameCount =
        animation.kind === 'sprite_sheet'
            ? animation.frameCount
            : animation.frames.length;
    const fallbackFrame =
        animation.kind === 'sprite_sheet'
            ? animation.reducedMotionFallback.frame
            : 0;
    const animationKey = `${source}:${fallbackFrame}:${reducedMotion}`;
    const initialFrame = reducedMotion ? fallbackFrame : 0;
    const frame =
        frameState.key === animationKey ? frameState.value : initialFrame;
    const status = statusState.key === source ? statusState.value : 'loading';

    useEffect(() => {
        let cancelled = false;
        const image = new Image();
        image.onload = () => {
            if (!cancelled) {
                setStatusState({ key: source, value: 'ready' });
            }
        };
        image.onerror = () => {
            if (!cancelled) {
                setStatusState({ key: source, value: 'error' });
            }
        };
        image.src = source;

        return () => {
            cancelled = true;
        };
    }, [source]);

    useEffect(() => {
        const updateDocumentVisibility = () =>
            setDocumentVisible(!document.hidden);
        document.addEventListener('visibilitychange', updateDocumentVisibility);

        return () =>
            document.removeEventListener(
                'visibilitychange',
                updateDocumentVisibility,
            );
    }, []);

    useEffect(() => {
        completedRef.current = false;
    }, [animationKey]);

    useEffect(() => {
        if (
            status !== 'ready' ||
            !isPlaying ||
            reducedMotion ||
            !documentVisible
        ) {
            return;
        }

        let animationFrameId = 0;
        let lastTimestamp = 0;
        const millisecondsPerFrame =
            1000 / ((animation.fps ?? fallbackFramesPerSecond) * playbackRate);
        const advance = (timestamp: number) => {
            if (lastTimestamp === 0) {
                lastTimestamp = timestamp;
            }

            if (timestamp - lastTimestamp >= millisecondsPerFrame) {
                lastTimestamp = timestamp;
                setFrameState((currentState) => {
                    const currentFrame =
                        currentState.key === animationKey
                            ? currentState.value
                            : initialFrame;
                    const nextFrame = currentFrame + 1;

                    if (nextFrame < frameCount) {
                        return { key: animationKey, value: nextFrame };
                    }

                    if (animation.loop) {
                        return { key: animationKey, value: 0 };
                    }

                    if (!completedRef.current) {
                        completedRef.current = true;
                        onComplete?.();
                    }

                    return { key: animationKey, value: frameCount - 1 };
                });
            }

            animationFrameId = requestAnimationFrame(advance);
        };

        animationFrameId = requestAnimationFrame(advance);

        return () => cancelAnimationFrame(animationFrameId);
    }, [
        animation.fps,
        animation.loop,
        animationKey,
        documentVisible,
        frameCount,
        initialFrame,
        isPlaying,
        onComplete,
        playbackRate,
        reducedMotion,
        status,
    ]);

    return { frame, status };
}
