import type { FamiliarManifest, FamiliarStageKey } from '@/types/familiars';

export type FamiliarVisualState =
    'idle' | 'listening' | 'thinking' | 'evolving' | 'celebrating';

export interface PrototypeQuest {
    key: keyof FamiliarManifest['quests'];
    label: string;
}

export interface FamiliarLabProps {
    manifest: FamiliarManifest;
    stageLabels: Record<FamiliarStageKey, string>;
    quests: PrototypeQuest[];
}
