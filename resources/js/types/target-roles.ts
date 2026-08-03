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
