import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Save, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { FormEvent, ReactNode } from 'react';
import ApplyRoleAnalysisProposalController from '@/actions/App/Http/Controllers/ApplyRoleAnalysisProposalController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    AnalysisCompetency,
    AnalysisResponsibility,
    CompetencyCategory,
    Priority,
    RoleAnalysisView,
    Seniority,
    TargetRoleOptions,
    TargetRoleProfile,
} from '@/types';

interface AmbiguityDecision {
    index: number;
    status: 'unresolved' | 'answered' | 'not_relevant';
    answer: string | null;
}
interface AssumptionDecision {
    index: number;
    status: 'unresolved' | 'accepted' | 'corrected' | 'removed';
    correction: string | null;
}
interface ReviewFormData {
    title: string;
    company_name: string;
    seniority: Seniority;
    role_summary: string;
    interview_at: string;
    biggest_concern: string;
    responsibilities: AnalysisResponsibility[];
    competencies: AnalysisCompetency[];
    ambiguities: AmbiguityDecision[];
    assumptions: AssumptionDecision[];
}

const selectClassName =
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive';

function localDateTime(value: string | null): string {
    return value ? value.slice(0, 16) : '';
}

function initialData(
    targetRole: TargetRoleProfile,
    analysis: RoleAnalysisView,
): ReviewFormData {
    const proposal = analysis.proposal!;
    const review = analysis.review;

    if (review) {
        return {
            title: review.reviewed_payload.title,
            company_name: review.reviewed_payload.company_name ?? '',
            seniority: review.reviewed_payload.seniority,
            role_summary: review.reviewed_payload.role_summary,
            interview_at: localDateTime(review.reviewed_payload.interview_at),
            biggest_concern: review.reviewed_payload.biggest_concern ?? '',
            responsibilities: review.reviewed_payload.responsibilities,
            competencies: review.reviewed_payload.competencies,
            ambiguities: proposal.ambiguities.map(
                (_, index) =>
                    review.ambiguity_responses.find(
                        (row) => row.index === index,
                    ) ?? { index, status: 'unresolved', answer: null },
            ),
            assumptions: proposal.assumptions.map(
                (_, index) =>
                    review.assumption_decisions.find(
                        (row) => row.index === index,
                    ) ?? { index, status: 'unresolved', correction: null },
            ),
        };
    }

    const competencies: AnalysisCompetency[] = proposal.competencies.map(
        (row, index) => ({
            proposal_type: 'competency',
            proposal_index: index,
            name: row.name,
            category: row.category,
            priority: row.priority,
            evidence: row.evidence,
            confidence: row.confidence,
            inferred: row.source === 'ai_inferred',
            user_confidence: null,
        }),
    );
    const seen = new Set(
        competencies.map(
            (row) => `${row.category}|${row.name.toLocaleLowerCase()}`,
        ),
    );
    proposal.technologies_and_tools.forEach((row, index) => {
        const key = `tooling|${row.name.toLocaleLowerCase()}`;

        if (!seen.has(key)) {
            seen.add(key);
            competencies.push({
                proposal_type: 'technology',
                proposal_index: index,
                name: row.name,
                category: 'tooling',
                priority: row.priority,
                evidence: row.evidence,
                confidence: row.confidence,
                inferred: row.source === 'ai_inferred',
                user_confidence: null,
            });
        }
    });

    return {
        title: targetRole.title ?? proposal.normalized_title ?? '',
        company_name: targetRole.company_name ?? proposal.company_name ?? '',
        seniority: targetRole.seniority ?? proposal.seniority,
        role_summary: targetRole.role_summary ?? proposal.role_summary,
        interview_at: localDateTime(targetRole.interview_at),
        biggest_concern: targetRole.biggest_concern ?? '',
        responsibilities: proposal.responsibilities.map((row, index) => ({
            proposal_index: index,
            content: row.content,
            priority: row.priority,
            evidence: row.evidence,
            confidence: row.confidence,
            inferred: row.source === 'ai_inferred',
        })),
        competencies,
        ambiguities: proposal.ambiguities.map((_, index) => ({
            index,
            status: 'unresolved',
            answer: null,
        })),
        assumptions: proposal.assumptions.map((_, index) => ({
            index,
            status: 'unresolved',
            correction: null,
        })),
    };
}

export function JobListingReviewForm({
    targetRole,
    analysis,
    options,
}: {
    targetRole: TargetRoleProfile;
    analysis: RoleAnalysisView;
    options: TargetRoleOptions;
}) {
    const proposal = analysis.proposal!;
    const form = useForm<ReviewFormData>(initialData(targetRole, analysis));
    const isSubmitting = useRef(false);

    useEffect(() => {
        const beforeUnload = (event: BeforeUnloadEvent) => {
            if (form.isDirty) {
                event.preventDefault();
            }
        };
        const removeBeforeVisit = router.on('before', (event) => {
            if (
                form.isDirty &&
                !isSubmitting.current &&
                !window.confirm('Leave without saving your review corrections?')
            ) {
                event.preventDefault();
            }
        });
        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            removeBeforeVisit();
        };
    }, [form.isDirty]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        isSubmitting.current = true;
        form.put(
            ApplyRoleAnalysisProposalController({ targetRole, analysis }).url,
            {
                preserveScroll: true,
                onFinish: () => {
                    isSubmitting.current = false;
                },
            },
        );
    }

    function focus(id: string) {
        window.requestAnimationFrame(() =>
            document.getElementById(id)?.focus(),
        );
    }
    function move(
        kind: 'responsibilities' | 'competencies',
        index: number,
        direction: -1 | 1,
    ) {
        const rows = [...form.data[kind]];
        const next = index + direction;
        [rows[index], rows[next]] = [rows[next], rows[index]];
        form.setData(kind, rows as never);
        focus(`${kind}-${next}-move-${direction * -1}`);
    }

    function remove(kind: 'responsibilities' | 'competencies', index: number) {
        const rows = form.data[kind].filter(
            (_, rowIndex) => rowIndex !== index,
        );
        form.setData(kind, rows as never);
        focus(
            rows.length
                ? `${kind}-${Math.min(index, rows.length - 1)}-remove`
                : `add-${kind}`,
        );
    }

    return (
        <form className="min-w-0 space-y-8" onSubmit={submit}>
            <section
                className="space-y-4 rounded-xl border bg-card p-4 sm:p-6"
                aria-labelledby="proposal-basics-heading"
            >
                <div>
                    <h2
                        id="proposal-basics-heading"
                        className="text-xl font-semibold"
                    >
                        Review the extracted basics
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        This proposal is not authoritative until you save and
                        confirm it.
                    </p>
                </div>
                <div className="grid min-w-0 gap-5 sm:grid-cols-2">
                    <Field
                        label="Role title"
                        id="title"
                        error={form.errors.title}
                    >
                        <Input
                            id="title"
                            autoFocus
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.title)}
                        />
                    </Field>
                    <Field
                        label="Company"
                        id="company_name"
                        error={form.errors.company_name}
                    >
                        <Input
                            id="company_name"
                            value={form.data.company_name}
                            onChange={(event) =>
                                form.setData('company_name', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Seniority"
                        id="seniority"
                        error={form.errors.seniority}
                    >
                        <select
                            id="seniority"
                            className={selectClassName}
                            value={form.data.seniority}
                            onChange={(event) =>
                                form.setData(
                                    'seniority',
                                    event.target.value as Seniority,
                                )
                            }
                        >
                            {options.seniorities.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Interview date and time (optional)"
                        id="interview_at"
                        error={form.errors.interview_at}
                    >
                        <Input
                            id="interview_at"
                            type="datetime-local"
                            value={form.data.interview_at}
                            onChange={(event) =>
                                form.setData('interview_at', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        className="sm:col-span-2"
                        label="Role summary"
                        id="role_summary"
                        error={form.errors.role_summary}
                    >
                        <Textarea
                            id="role_summary"
                            value={form.data.role_summary}
                            onChange={(event) =>
                                form.setData('role_summary', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        className="sm:col-span-2"
                        label="Biggest concern (optional)"
                        id="biggest_concern"
                        error={form.errors.biggest_concern}
                    >
                        <Textarea
                            id="biggest_concern"
                            value={form.data.biggest_concern}
                            onChange={(event) =>
                                form.setData(
                                    'biggest_concern',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                </div>
                <p className="text-sm">
                    <strong>Overall extraction confidence:</strong>{' '}
                    {proposal.overall_extraction_confidence}%
                </p>
            </section>

            <ReviewRowsHeading
                title="Responsibilities"
                description="Edit, remove, or reorder the accountabilities that belong in your profile."
                error={form.errors.responsibilities}
            />
            <div className="space-y-4">
                {form.data.responsibilities.map((row, index) => (
                    <ReviewCard
                        key={`${row.proposal_index ?? 'new'}-${index}`}
                        inferred={row.inferred}
                        evidence={row.evidence}
                        confidence={row.confidence}
                    >
                        <div className="grid min-w-0 gap-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto]">
                            <Field
                                label={`Responsibility ${index + 1}`}
                                id={`responsibility-${index}-content`}
                                error={
                                    form.errors[
                                        `responsibilities.${index}.content`
                                    ]
                                }
                            >
                                <Textarea
                                    id={`responsibility-${index}-content`}
                                    value={row.content}
                                    onChange={(event) => {
                                        const rows = [
                                            ...form.data.responsibilities,
                                        ];
                                        rows[index] = {
                                            ...row,
                                            content: event.target.value,
                                        };
                                        form.setData('responsibilities', rows);
                                    }}
                                />
                            </Field>
                            <Field
                                label="Priority"
                                id={`responsibility-${index}-priority`}
                                error={
                                    form.errors[
                                        `responsibilities.${index}.priority`
                                    ]
                                }
                            >
                                <PrioritySelect
                                    id={`responsibility-${index}-priority`}
                                    value={row.priority}
                                    options={options}
                                    onChange={(priority) => {
                                        const rows = [
                                            ...form.data.responsibilities,
                                        ];
                                        rows[index] = { ...row, priority };
                                        form.setData('responsibilities', rows);
                                    }}
                                />
                            </Field>
                            <RowControls
                                kind="responsibilities"
                                index={index}
                                count={form.data.responsibilities.length}
                                onMove={(direction) =>
                                    move('responsibilities', index, direction)
                                }
                                onRemove={() =>
                                    remove('responsibilities', index)
                                }
                            />
                        </div>
                    </ReviewCard>
                ))}
                <Button
                    id="add-responsibilities"
                    type="button"
                    variant="outline"
                    disabled={form.data.responsibilities.length >= 20}
                    onClick={() => {
                        const index = form.data.responsibilities.length;
                        form.setData('responsibilities', [
                            ...form.data.responsibilities,
                            {
                                proposal_index: null,
                                content: '',
                                priority: 'medium',
                                evidence: null,
                                confidence: null,
                                inferred: false,
                            },
                        ]);
                        focus(`responsibility-${index}-content`);
                    }}
                >
                    <Plus /> Add responsibility
                </Button>
            </div>

            <ReviewRowsHeading
                title="Competencies and technologies"
                description="Technologies are saved once as tooling competencies to avoid conflicting records."
                error={form.errors.competencies}
            />
            <div className="space-y-4">
                {form.data.competencies.map((row, index) => (
                    <ReviewCard
                        key={`${row.proposal_type ?? 'new'}-${row.proposal_index ?? index}`}
                        inferred={row.inferred}
                        evidence={row.evidence}
                        confidence={row.confidence}
                    >
                        <div className="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_9rem_9rem_8rem_auto]">
                            <Field
                                label={`Competency ${index + 1}`}
                                id={`competency-${index}-name`}
                                error={
                                    form.errors[`competencies.${index}.name`]
                                }
                            >
                                <Input
                                    id={`competency-${index}-name`}
                                    value={row.name}
                                    onChange={(event) => {
                                        const rows = [
                                            ...form.data.competencies,
                                        ];
                                        rows[index] = {
                                            ...row,
                                            name: event.target.value,
                                        };
                                        form.setData('competencies', rows);
                                    }}
                                />
                            </Field>
                            <Field
                                label="Category"
                                id={`competency-${index}-category`}
                                error={
                                    form.errors[
                                        `competencies.${index}.category`
                                    ]
                                }
                            >
                                <select
                                    id={`competency-${index}-category`}
                                    className={selectClassName}
                                    value={row.category}
                                    onChange={(event) => {
                                        const rows = [
                                            ...form.data.competencies,
                                        ];
                                        rows[index] = {
                                            ...row,
                                            category: event.target
                                                .value as CompetencyCategory,
                                        };
                                        form.setData('competencies', rows);
                                    }}
                                >
                                    {options.competencyCategories.map(
                                        (option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                            <Field
                                label="Priority"
                                id={`competency-${index}-priority`}
                                error={
                                    form.errors[
                                        `competencies.${index}.priority`
                                    ]
                                }
                            >
                                <PrioritySelect
                                    id={`competency-${index}-priority`}
                                    value={row.priority}
                                    options={options}
                                    onChange={(priority) => {
                                        const rows = [
                                            ...form.data.competencies,
                                        ];
                                        rows[index] = { ...row, priority };
                                        form.setData('competencies', rows);
                                    }}
                                />
                            </Field>
                            <Field
                                label="Your confidence"
                                id={`competency-${index}-user-confidence`}
                                error={
                                    form.errors[
                                        `competencies.${index}.user_confidence`
                                    ]
                                }
                            >
                                <select
                                    id={`competency-${index}-user-confidence`}
                                    className={selectClassName}
                                    value={row.user_confidence ?? ''}
                                    onChange={(event) => {
                                        const rows = [
                                            ...form.data.competencies,
                                        ];
                                        rows[index] = {
                                            ...row,
                                            user_confidence: event.target.value
                                                ? Number(event.target.value)
                                                : null,
                                        };
                                        form.setData('competencies', rows);
                                    }}
                                >
                                    <option value="">Not set</option>
                                    {[1, 2, 3, 4, 5].map((value) => (
                                        <option key={value} value={value}>
                                            {value} of 5
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <RowControls
                                kind="competencies"
                                index={index}
                                count={form.data.competencies.length}
                                onMove={(direction) =>
                                    move('competencies', index, direction)
                                }
                                onRemove={() => remove('competencies', index)}
                            />
                        </div>
                    </ReviewCard>
                ))}
                <Button
                    id="add-competencies"
                    type="button"
                    variant="outline"
                    disabled={form.data.competencies.length >= 50}
                    onClick={() => {
                        const index = form.data.competencies.length;
                        form.setData('competencies', [
                            ...form.data.competencies,
                            {
                                proposal_type: null,
                                proposal_index: null,
                                name: '',
                                category: 'technical',
                                priority: 'medium',
                                evidence: null,
                                confidence: null,
                                inferred: false,
                                user_confidence: null,
                            },
                        ]);
                        focus(`competency-${index}-name`);
                    }}
                >
                    <Plus /> Add competency
                </Button>
            </div>

            <section className="space-y-4" aria-labelledby="focus-heading">
                <div>
                    <h2 id="focus-heading" className="text-xl font-semibold">
                        Preparation suggestions
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        These are likely focus areas, not guaranteed interview
                        questions.
                    </p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    {proposal.interview_focus_areas.map((area, index) => (
                        <Card key={`${area.focus}-${index}`}>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {area.focus}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p>{area.rationale}</p>
                                <p>
                                    <strong>Confidence:</strong>{' '}
                                    {area.confidence}%
                                </p>
                                {area.evidence && (
                                    <p className="text-muted-foreground">
                                        <strong>Listing evidence:</strong> “
                                        {area.evidence}”
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>

            <section
                className="space-y-4"
                aria-labelledby="ambiguities-heading"
            >
                <div>
                    <h2
                        id="ambiguities-heading"
                        className="text-xl font-semibold"
                    >
                        Clarification questions
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Answer, mark not relevant, or leave unresolved.
                    </p>
                </div>
                {proposal.ambiguities.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No ambiguities were identified.
                    </p>
                ) : (
                    proposal.ambiguities.map((item, index) => {
                        const decision = form.data.ambiguities[index];

                        return (
                            <Card key={`${item.question}-${index}`}>
                                <CardContent className="grid min-w-0 gap-4 pt-6 sm:grid-cols-[minmax(0,1fr)_12rem]">
                                    <div className="min-w-0 space-y-2">
                                        <Badge variant="outline">
                                            {item.area.replaceAll('_', ' ')}
                                        </Badge>
                                        <h3 className="font-semibold">
                                            {item.question}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {item.reason}
                                        </p>
                                        {decision.status === 'answered' && (
                                            <Field
                                                label="Your answer"
                                                id={`ambiguity-${index}-answer`}
                                                error={
                                                    form.errors[
                                                        `ambiguities.${index}.answer`
                                                    ]
                                                }
                                            >
                                                <Textarea
                                                    id={`ambiguity-${index}-answer`}
                                                    value={
                                                        decision.answer ?? ''
                                                    }
                                                    onChange={(event) => {
                                                        const rows = [
                                                            ...form.data
                                                                .ambiguities,
                                                        ];
                                                        rows[index] = {
                                                            ...decision,
                                                            answer: event.target
                                                                .value,
                                                        };
                                                        form.setData(
                                                            'ambiguities',
                                                            rows,
                                                        );
                                                    }}
                                                />
                                            </Field>
                                        )}
                                    </div>
                                    <Field
                                        label="Decision"
                                        id={`ambiguity-${index}-status`}
                                    >
                                        <select
                                            id={`ambiguity-${index}-status`}
                                            className={selectClassName}
                                            value={decision.status}
                                            onChange={(event) => {
                                                const rows = [
                                                    ...form.data.ambiguities,
                                                ];
                                                rows[index] = {
                                                    ...decision,
                                                    status: event.target
                                                        .value as AmbiguityDecision['status'],
                                                    answer:
                                                        event.target.value ===
                                                        'answered'
                                                            ? decision.answer
                                                            : null,
                                                };
                                                form.setData(
                                                    'ambiguities',
                                                    rows,
                                                );
                                            }}
                                        >
                                            <option value="unresolved">
                                                Leave unresolved
                                            </option>
                                            <option value="answered">
                                                Answer
                                            </option>
                                            <option value="not_relevant">
                                                Not relevant
                                            </option>
                                        </select>
                                    </Field>
                                </CardContent>
                            </Card>
                        );
                    })
                )}
            </section>

            <section
                className="space-y-4"
                aria-labelledby="assumptions-heading"
            >
                <div>
                    <h2
                        id="assumptions-heading"
                        className="text-xl font-semibold"
                    >
                        Assumptions
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Every AI assumption stays visible until you accept,
                        correct, or remove it.
                    </p>
                </div>
                {proposal.assumptions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No assumptions were reported.
                    </p>
                ) : (
                    proposal.assumptions.map((item, index) => {
                        const decision = form.data.assumptions[index];

                        return (
                            <Card key={`${item.statement}-${index}`}>
                                <CardContent className="grid min-w-0 gap-4 pt-6 sm:grid-cols-[minmax(0,1fr)_12rem]">
                                    <div className="min-w-0 space-y-2">
                                        <Badge variant="secondary">
                                            Assumption ·{' '}
                                            {item.area.replaceAll('_', ' ')}
                                        </Badge>
                                        <h3 className="font-semibold">
                                            {item.statement}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            Why it was inferred: {item.reason}
                                        </p>
                                        {decision.status === 'corrected' && (
                                            <Field
                                                label="Your correction"
                                                id={`assumption-${index}-correction`}
                                                error={
                                                    form.errors[
                                                        `assumptions.${index}.correction`
                                                    ]
                                                }
                                            >
                                                <Textarea
                                                    id={`assumption-${index}-correction`}
                                                    value={
                                                        decision.correction ??
                                                        ''
                                                    }
                                                    onChange={(event) => {
                                                        const rows = [
                                                            ...form.data
                                                                .assumptions,
                                                        ];
                                                        rows[index] = {
                                                            ...decision,
                                                            correction:
                                                                event.target
                                                                    .value,
                                                        };
                                                        form.setData(
                                                            'assumptions',
                                                            rows,
                                                        );
                                                    }}
                                                />
                                            </Field>
                                        )}
                                    </div>
                                    <Field
                                        label="Decision"
                                        id={`assumption-${index}-status`}
                                    >
                                        <select
                                            id={`assumption-${index}-status`}
                                            className={selectClassName}
                                            value={decision.status}
                                            onChange={(event) => {
                                                const rows = [
                                                    ...form.data.assumptions,
                                                ];
                                                rows[index] = {
                                                    ...decision,
                                                    status: event.target
                                                        .value as AssumptionDecision['status'],
                                                    correction:
                                                        event.target.value ===
                                                        'corrected'
                                                            ? decision.correction
                                                            : null,
                                                };
                                                form.setData(
                                                    'assumptions',
                                                    rows,
                                                );
                                            }}
                                        >
                                            <option value="unresolved">
                                                Not decided
                                            </option>
                                            <option value="accepted">
                                                Accept
                                            </option>
                                            <option value="corrected">
                                                Correct
                                            </option>
                                            <option value="removed">
                                                Remove
                                            </option>
                                        </select>
                                    </Field>
                                </CardContent>
                            </Card>
                        );
                    })
                )}
            </section>

            <InputError
                message={(form.errors as Record<string, string>).analysis}
            />
            <div className="sticky bottom-3 flex flex-wrap items-center gap-3 rounded-xl border bg-background/95 p-3 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-background/80">
                <Button type="submit" disabled={form.processing}>
                    <Save />{' '}
                    {form.processing
                        ? 'Saving review…'
                        : 'Save review corrections'}
                </Button>
                <span
                    className="text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {form.recentlySuccessful
                        ? 'Review saved.'
                        : form.isDirty
                          ? 'Unsaved changes'
                          : analysis.review
                            ? `Last saved ${new Date(analysis.review.applied_at).toLocaleString()}`
                            : 'Save before confirmation.'}
                </span>
            </div>
        </form>
    );
}

function Field({
    label,
    id,
    error,
    className,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`grid min-w-0 content-start gap-2 ${className ?? ''}`}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function ReviewRowsHeading({
    title,
    description,
    error,
}: {
    title: string;
    description: string;
    error?: string;
}) {
    return (
        <div>
            <h2 className="text-xl font-semibold">{title}</h2>
            <p className="text-sm text-muted-foreground">{description}</p>
            <InputError message={error} />
        </div>
    );
}
function ReviewCard({
    inferred,
    evidence,
    confidence,
    children,
}: {
    inferred: boolean;
    evidence: string | null;
    confidence: number | null;
    children: ReactNode;
}) {
    return (
        <Card className="min-w-0">
            <CardContent className="space-y-4 pt-6">
                {children}
                <div className="grid min-w-0 gap-2 border-t pt-3 text-sm sm:grid-cols-2">
                    <p>
                        <strong>Source:</strong>{' '}
                        {inferred ? (
                            <Badge variant="secondary">AI inferred</Badge>
                        ) : (
                            'Job listing'
                        )}
                    </p>
                    <p>
                        <strong>Extraction confidence:</strong>{' '}
                        {confidence === null
                            ? 'Not applicable — user added'
                            : `${confidence}%`}
                    </p>
                    <p className="break-words text-muted-foreground sm:col-span-2">
                        <strong>Listing evidence:</strong>{' '}
                        {evidence
                            ? `“${evidence}”`
                            : 'No direct listing evidence.'}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
function PrioritySelect({
    id,
    value,
    options,
    onChange,
}: {
    id: string;
    value: Priority;
    options: TargetRoleOptions;
    onChange: (priority: Priority) => void;
}) {
    return (
        <select
            id={id}
            className={selectClassName}
            value={value}
            onChange={(event) => onChange(event.target.value as Priority)}
        >
            {options.priorities.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}
function RowControls({
    kind,
    index,
    count,
    onMove,
    onRemove,
}: {
    kind: 'responsibilities' | 'competencies';
    index: number;
    count: number;
    onMove: (direction: -1 | 1) => void;
    onRemove: () => void;
}) {
    const label = kind === 'responsibilities' ? 'responsibility' : 'competency';

    return (
        <div className="flex items-start gap-1 sm:col-span-2 lg:col-span-1">
            <Button
                id={`${kind}-${index}-move--1`}
                type="button"
                size="icon"
                variant="ghost"
                disabled={index === 0}
                aria-label={`Move ${label} ${index + 1} up`}
                onClick={() => onMove(-1)}
            >
                <ArrowUp />
            </Button>
            <Button
                id={`${kind}-${index}-move-1`}
                type="button"
                size="icon"
                variant="ghost"
                disabled={index === count - 1}
                aria-label={`Move ${label} ${index + 1} down`}
                onClick={() => onMove(1)}
            >
                <ArrowDown />
            </Button>
            <Button
                id={`${kind}-${index}-remove`}
                type="button"
                size="icon"
                variant="ghost"
                aria-label={`Remove ${label} ${index + 1}`}
                onClick={onRemove}
            >
                <Trash2 />
            </Button>
        </div>
    );
}
