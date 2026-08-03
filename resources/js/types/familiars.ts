import type { FamiliarReaction } from '@/types/progression';

export type FamiliarInteractionIntent =
    | 'teach'
    | 'ask_question'
    | 'interview_prep'
    | 'practice_topic'
    | 'custom_question';

export interface FamiliarTopic {
    id: string;
    label: string;
    type:
        | 'competency'
        | 'technology'
        | 'responsibility'
        | 'focus_area'
        | 'clarification';
    priority: 'low' | 'medium' | 'high' | 'critical';
}

export interface FamiliarNextAction {
    type: FamiliarInteractionIntent;
    label: string;
    topic_id?: string;
}

export interface RoleGroundedCoachingResponse {
    title: string;
    content: string;
    reaction: FamiliarReaction;
    next_actions: FamiliarNextAction[];
}

export type FamiliarStageKey = 'stage-1' | 'stage-2' | 'stage-3';

export interface ReducedMotionFallback {
    src: string;
    frame: number;
}

export interface SpriteSheetAnimation {
    kind: 'sprite_sheet';
    src: string;
    frameWidth: number;
    frameHeight: number;
    frameCount: number;
    columns: number;
    rows: number;
    fps: number | null;
    loop: boolean;
    reducedMotionFallback: ReducedMotionFallback;
}

export interface FrameSequenceAnimation {
    kind: 'frame_sequence';
    frames: string[];
    frameWidth: number;
    frameHeight: number;
    fps: number | null;
    loop: boolean;
    reducedMotionFallback: { src: string };
}

export interface StaticImageAsset {
    kind: 'image';
    src: string;
    frameWidth: number;
    frameHeight: number;
    frameCount: 1;
    columns: 1;
    rows: 1;
}

export type FamiliarAnimation = SpriteSheetAnimation | FrameSequenceAnimation;

export interface FamiliarStage {
    number: number;
    idle: SpriteSheetAnimation;
}

export interface EvolutionStage {
    sourceStage: FamiliarStageKey;
    destinationStage: FamiliarStageKey;
    animation: SpriteSheetAnimation;
}

export interface OverlayPosition {
    anchor: 'top-left' | 'top-center' | 'top-right' | 'center';
    x: number;
    y: number;
}

export interface OverlayAsset {
    key: 'thinking' | 'listening';
    position: OverlayPosition | null;
    animation: SpriteSheetAnimation;
}

export interface QuestIconAsset {
    key: 'scroll-1' | 'scroll-2' | 'observatory-lens' | 'magical-book';
    icon: StaticImageAsset;
}

export interface FamiliarManifest {
    key: string;
    name: string;
    version: number;
    stages: Record<FamiliarStageKey, FamiliarStage>;
    evolutions: Record<'stage-1-to-2' | 'stage-2-to-3', EvolutionStage>;
    overlays: Record<'thinking' | 'listening', OverlayAsset>;
    quests: Record<QuestIconAsset['key'], QuestIconAsset>;
}
