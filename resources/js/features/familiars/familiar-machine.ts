import type { FamiliarStageKey } from '@/types/familiars';
import type { FamiliarVisualState } from './types';

export interface FamiliarMachineState {
    stage: FamiliarStageKey;
    visualState: FamiliarVisualState;
    evolutionKey: 'stage-1-to-2' | 'stage-2-to-3' | null;
    paused: boolean;
    playbackRate: number;
}

export const initialFamiliarMachineState: FamiliarMachineState = {
    stage: 'stage-1',
    visualState: 'idle',
    evolutionKey: null,
    paused: false,
    playbackRate: 1,
};
