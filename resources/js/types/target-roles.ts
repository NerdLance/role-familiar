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
}

export interface TargetRoleCompetency {
    id?: number;
    name: string;
    category: CompetencyCategory;
    priority: Priority;
    source_evidence: string | null;
    user_confidence: number | null;
}

export interface TargetRoleSummary {
    public_id: string;
    status: TargetRoleStatus;
    source_type: TargetRoleSourceType;
    title: string;
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
