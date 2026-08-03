export type TargetRoleStatus =
    | 'draft'
    | 'analysis_pending'
    | 'review_required'
    | 'confirmed'
    | 'analysis_failed'
    | 'archived';

export type TargetRoleSourceType = 'manual' | 'job_listing';

export type Seniority =
    | 'intern'
    | 'junior'
    | 'mid'
    | 'senior'
    | 'staff'
    | 'principal'
    | 'lead'
    | 'manager'
    | 'director'
    | 'executive'
    | 'unspecified';

export type CompetencyCategory =
    | 'technical'
    | 'behavioral'
    | 'leadership'
    | 'domain'
    | 'tooling'
    | 'communication'
    | 'delivery';

export type Priority = 'low' | 'medium' | 'high' | 'critical';

export type DataSource =
    'user' | 'job_listing' | 'ai_inferred' | 'user_corrected';

export type RoleAnalysisStatus =
    'pending' | 'processing' | 'completed' | 'failed';

export interface TargetRoleResponsibility {
    id?: number;
    content: string;
    priority: Priority;
    source: DataSource;
    source_evidence: string | null;
}

export interface TargetRoleCompetency {
    id?: number;
    name: string;
    category: CompetencyCategory;
    priority: Priority;
    source_evidence: string | null;
    user_confidence: number | null;
    source: DataSource;
}

export interface TargetRoleSummary {
    public_id: string;
    status: TargetRoleStatus;
    source_type: TargetRoleSourceType;
    title: string | null;
    company_name: string | null;
    seniority: Seniority | null;
    role_summary: string | null;
    interview_at: string | null;
    biggest_concern: string | null;
    confirmed_at: string | null;
    archived_at: string | null;
    is_active: boolean;
    updated_at: string;
}

export interface AnalysisResponsibility {
    proposal_index: number | null;
    id?: number;
    content: string;
    priority: Priority;
    evidence: string | null;
    confidence: number | null;
    inferred: boolean;
    source?: DataSource;
}

export interface AnalysisCompetency {
    proposal_type: 'competency' | 'technology' | null;
    proposal_index: number | null;
    id?: number;
    name: string;
    category: CompetencyCategory;
    priority: Priority;
    evidence: string | null;
    confidence: number | null;
    inferred: boolean;
    source?: DataSource;
    user_confidence: number | null;
}

export interface AnalysisAmbiguity {
    area: string;
    question: string;
    reason: string;
}

export interface AnalysisAssumption {
    area: string;
    statement: string;
    reason: string;
}

export interface JobListingAnalysisProposal {
    normalized_title: string | null;
    company_name: string | null;
    seniority: Seniority;
    role_summary: string;
    responsibilities: Array<{
        content: string;
        priority: Priority;
        evidence: string | null;
        source: 'job_listing' | 'ai_inferred';
        confidence: number;
    }>;
    competencies: Array<{
        name: string;
        category: CompetencyCategory;
        priority: Priority;
        evidence: string | null;
        source: 'job_listing' | 'ai_inferred';
        confidence: number;
    }>;
    technologies_and_tools: Array<{
        name: string;
        priority: Priority;
        evidence: string | null;
        source: 'job_listing' | 'ai_inferred';
        confidence: number;
    }>;
    interview_focus_areas: Array<{
        focus: string;
        rationale: string;
        priority: Priority;
        evidence: string | null;
        source: 'job_listing' | 'ai_inferred';
        confidence: number;
    }>;
    ambiguities: AnalysisAmbiguity[];
    assumptions: AnalysisAssumption[];
    overall_extraction_confidence: number;
}

export interface RoleAnalysisReview {
    reviewed_payload: {
        title: string;
        company_name: string | null;
        seniority: Seniority;
        role_summary: string;
        interview_at: string | null;
        biggest_concern: string | null;
        responsibilities: AnalysisResponsibility[];
        competencies: AnalysisCompetency[];
    };
    ambiguity_responses: Array<{
        index: number;
        status: 'unresolved' | 'answered' | 'not_relevant';
        answer: string | null;
    }>;
    assumption_decisions: Array<{
        index: number;
        status: 'unresolved' | 'accepted' | 'corrected' | 'removed';
        correction: string | null;
    }>;
    applied_at: string;
}

export interface RoleAnalysisView {
    public_id: string;
    status: RoleAnalysisStatus;
    failure_message: string | null;
    proposal: JobListingAnalysisProposal | null;
    review: RoleAnalysisReview | null;
}

export interface QueueWyrmRoleVisual {
    name: string;
    idle: SpriteSheetAnimation;
    overlays: Record<'thinking' | 'listening', OverlayAsset>;
}

export interface TargetRoleProfile extends TargetRoleSummary {
    responsibilities: TargetRoleResponsibility[];
    competencies: TargetRoleCompetency[];
}

export interface TargetRoleOptions {
    seniorities: Array<{ value: Seniority; label: string }>;
    priorities: Array<{ value: Priority; label: string }>;
    competencyCategories: Array<{
        value: CompetencyCategory;
        label: string;
    }>;
}
import type { OverlayAsset, SpriteSheetAnimation } from './familiars';
