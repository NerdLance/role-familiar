import { useCallback, useEffect, useState } from 'react';
import type { FamiliarManifest, FamiliarStageKey } from '@/types/familiars';
import { initialFamiliarMachineState } from '../familiar-machine';

export function useFamiliarMachine(
    manifest: FamiliarManifest,
    reducedMotion: boolean,
) {
    const [machine, setMachine] = useState(initialFamiliarMachineState);

    const selectStage = useCallback((stage: FamiliarStageKey) => {
        setMachine((current) =>
            current.visualState === 'evolving'
                ? current
                : {
                      ...current,
                      stage,
                      visualState: 'idle',
                      evolutionKey: null,
                  },
        );
    }, []);

    const toggleOverlay = useCallback((overlay: 'listening' | 'thinking') => {
        setMachine((current) => {
            if (current.visualState === 'evolving') {
                return current;
            }

            return {
                ...current,
                visualState: current.visualState === overlay ? 'idle' : overlay,
            };
        });
    }, []);

    const triggerEvolution = useCallback(
        (transition: 'stage-1-to-2' | 'stage-2-to-3') => {
            const evolution = manifest.evolutions[transition];

            setMachine((current) => {
                if (
                    current.visualState === 'evolving' ||
                    current.stage !== evolution.sourceStage
                ) {
                    return current;
                }

                if (reducedMotion) {
                    return {
                        ...current,
                        stage: evolution.destinationStage,
                        visualState: 'idle',
                        evolutionKey: null,
                    };
                }

                return {
                    ...current,
                    visualState: 'evolving',
                    evolutionKey: transition,
                };
            });
        },
        [manifest.evolutions, reducedMotion],
    );

    const finishEvolution = useCallback(() => {
        setMachine((current) => {
            if (!current.evolutionKey) {
                return current;
            }

            return {
                ...current,
                stage: manifest.evolutions[current.evolutionKey]
                    .destinationStage,
                visualState: 'idle',
                evolutionKey: null,
            };
        });
    }, [manifest.evolutions]);

    const celebrate = useCallback(() => {
        setMachine((current) =>
            current.visualState === 'evolving'
                ? current
                : { ...current, visualState: 'celebrating' },
        );
    }, []);

    useEffect(() => {
        if (reducedMotion && machine.evolutionKey) {
            const animationFrame = window.requestAnimationFrame(() => {
                setMachine((current) => {
                    if (!current.evolutionKey) {
                        return current;
                    }

                    return {
                        ...current,
                        stage: manifest.evolutions[current.evolutionKey]
                            .destinationStage,
                        visualState: 'idle',
                        evolutionKey: null,
                    };
                });
            });

            return () => window.cancelAnimationFrame(animationFrame);
        }
    }, [machine.evolutionKey, manifest.evolutions, reducedMotion]);

    useEffect(() => {
        if (machine.visualState !== 'celebrating') {
            return;
        }

        const timeout = window.setTimeout(() => {
            setMachine((current) => ({ ...current, visualState: 'idle' }));
        }, 1200);

        return () => window.clearTimeout(timeout);
    }, [machine.visualState]);

    return {
        ...machine,
        selectStage,
        toggleOverlay,
        triggerEvolution,
        finishEvolution,
        celebrate,
        reset: () => setMachine(initialFamiliarMachineState),
        togglePaused: () =>
            setMachine((current) => ({ ...current, paused: !current.paused })),
        setPlaybackRate: (playbackRate: number) =>
            setMachine((current) => ({ ...current, playbackRate })),
    };
}
