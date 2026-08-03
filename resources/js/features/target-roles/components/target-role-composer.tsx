import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/target-roles';
import type {
    CompetencyCategory,
    Priority,
    Seniority,
    TargetRoleOptions,
    TargetRoleProfile,
} from '@/types';

interface ResponsibilityRow {
    id?: number;
    content: string;
    priority: Priority;
}

interface CompetencyRow {
    id?: number;
    name: string;
    category: CompetencyCategory;
    priority: Priority;
    source_evidence: string;
    user_confidence: string;
}

interface TargetRoleFormData {
    title: string;
    company_name: string;
    seniority: Seniority | '';
    role_summary: string;
    interview_at: string;
    biggest_concern: string;
    responsibilities: ResponsibilityRow[];
    competencies: CompetencyRow[];
}

const selectClassName =
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive';

const emptyResponsibility = (): ResponsibilityRow => ({
    content: '',
    priority: 'medium',
});

const emptyCompetency = (): CompetencyRow => ({
    name: '',
    category: 'technical',
    priority: 'medium',
    source_evidence: '',
    user_confidence: '',
});

function localDateTime(value: string | null): string {
    return value ? value.slice(0, 16) : '';
}

export function TargetRoleComposer({
    options,
    targetRole,
}: {
    options: TargetRoleOptions;
    targetRole?: TargetRoleProfile;
}) {
    const form = useForm<TargetRoleFormData>({
        title: targetRole?.title ?? '',
        company_name: targetRole?.company_name ?? '',
        seniority: targetRole?.seniority ?? '',
        role_summary: targetRole?.role_summary ?? '',
        interview_at: localDateTime(targetRole?.interview_at ?? null),
        biggest_concern: targetRole?.biggest_concern ?? '',
        responsibilities: targetRole?.responsibilities.length
            ? targetRole.responsibilities.map((row) => ({ ...row }))
            : [emptyResponsibility()],
        competencies: targetRole?.competencies.length
            ? targetRole.competencies.map((row) => ({
                  ...row,
                  source_evidence: row.source_evidence ?? '',
                  user_confidence: row.user_confidence?.toString() ?? '',
              }))
            : [emptyCompetency()],
    });
    const isSubmitting = useRef(false);

    useEffect(() => {
        const warnBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!form.isDirty) {
                return;
            }

            event.preventDefault();
        };
        const removeBeforeVisit = router.on('before', (event) => {
            if (
                form.isDirty &&
                !isSubmitting.current &&
                !window.confirm(
                    'Leave without saving your target role changes?',
                )
            ) {
                event.preventDefault();
            }
        });

        window.addEventListener('beforeunload', warnBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', warnBeforeUnload);
            removeBeforeVisit();
        };
    }, [form.isDirty]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        isSubmitting.current = true;
        const options = {
            onFinish: () => {
                isSubmitting.current = false;
            },
        };

        if (targetRole) {
            form.put(update(targetRole).url, options);

            return;
        }

        form.post(store().url, options);
    }

    function focusAfterRender(id: string) {
        window.requestAnimationFrame(() =>
            document.getElementById(id)?.focus(),
        );
    }

    function updateResponsibility(
        index: number,
        patch: Partial<ResponsibilityRow>,
    ) {
        const rows = [...form.data.responsibilities];
        rows[index] = { ...rows[index], ...patch };
        form.setData('responsibilities', rows);
    }

    function updateCompetency(index: number, patch: Partial<CompetencyRow>) {
        const rows = [...form.data.competencies];
        rows[index] = { ...rows[index], ...patch };
        form.setData('competencies', rows);
    }

    function moveRow(
        kind: 'responsibilities' | 'competencies',
        index: number,
        direction: -1 | 1,
    ) {
        const nextIndex = index + direction;

        if (kind === 'responsibilities') {
            const rows = [...form.data.responsibilities];
            [rows[index], rows[nextIndex]] = [rows[nextIndex], rows[index]];
            form.setData('responsibilities', rows);
        } else {
            const rows = [...form.data.competencies];
            [rows[index], rows[nextIndex]] = [rows[nextIndex], rows[index]];
            form.setData('competencies', rows);
        }

        focusAfterRender(`${kind}-${nextIndex}-move-${direction * -1}`);
    }

    function removeResponsibility(index: number) {
        const rows = form.data.responsibilities.filter(
            (_, rowIndex) => rowIndex !== index,
        );
        form.setData('responsibilities', rows);
        focusAfterRender(
            rows.length
                ? `responsibilities-${Math.min(index, rows.length - 1)}-remove`
                : 'add-responsibility',
        );
    }

    function removeCompetency(index: number) {
        const rows = form.data.competencies.filter(
            (_, rowIndex) => rowIndex !== index,
        );
        form.setData('competencies', rows);
        focusAfterRender(
            rows.length
                ? `competencies-${Math.min(index, rows.length - 1)}-remove`
                : 'add-competency',
        );
    }

    return (
        <form className="min-w-0 space-y-8" onSubmit={submit}>
            <section className="grid min-w-0 gap-5 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2 sm:p-6">
                <div className="grid min-w-0 gap-2 sm:col-span-2">
                    <Label htmlFor="title">Role title</Label>
                    <Input
                        id="title"
                        autoFocus
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.title)}
                    />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="company_name">Company (optional)</Label>
                    <Input
                        id="company_name"
                        value={form.data.company_name}
                        onChange={(event) =>
                            form.setData('company_name', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.company_name)}
                    />
                    <InputError message={form.errors.company_name} />
                </div>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="seniority">Seniority (optional)</Label>
                    <select
                        id="seniority"
                        className={selectClassName}
                        value={form.data.seniority}
                        onChange={(event) =>
                            form.setData(
                                'seniority',
                                event.target.value as Seniority | '',
                            )
                        }
                        aria-invalid={Boolean(form.errors.seniority)}
                    >
                        <option value="">Not specified</option>
                        {options.seniorities.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.seniority} />
                </div>
                <div className="grid min-w-0 gap-2 sm:col-span-2">
                    <Label htmlFor="role_summary">
                        Role summary (optional)
                    </Label>
                    <Textarea
                        id="role_summary"
                        value={form.data.role_summary}
                        onChange={(event) =>
                            form.setData('role_summary', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.role_summary)}
                    />
                    <InputError message={form.errors.role_summary} />
                </div>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="interview_at">
                        Interview date and time (optional)
                    </Label>
                    <Input
                        id="interview_at"
                        type="datetime-local"
                        value={form.data.interview_at}
                        onChange={(event) =>
                            form.setData('interview_at', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.interview_at)}
                    />
                    <InputError message={form.errors.interview_at} />
                </div>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="biggest_concern">
                        Biggest concern (optional)
                    </Label>
                    <Textarea
                        id="biggest_concern"
                        className="min-h-20"
                        value={form.data.biggest_concern}
                        onChange={(event) =>
                            form.setData('biggest_concern', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.biggest_concern)}
                    />
                    <InputError message={form.errors.biggest_concern} />
                </div>
            </section>

            <section
                className="min-w-0 space-y-4"
                aria-labelledby="responsibilities-heading"
            >
                <div>
                    <h2
                        id="responsibilities-heading"
                        className="text-xl font-semibold"
                    >
                        Responsibilities
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        List what this role is accountable for, in priority
                        order.
                    </p>
                </div>
                <InputError message={form.errors.responsibilities} />
                {form.data.responsibilities.map((row, index) => (
                    <div
                        key={row.id ?? `responsibility-${index}`}
                        className="grid min-w-0 gap-4 rounded-xl border bg-card p-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto]"
                    >
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor={`responsibility-${index}-content`}>
                                Responsibility {index + 1}
                            </Label>
                            <Textarea
                                id={`responsibility-${index}-content`}
                                className="min-h-20"
                                value={row.content}
                                onChange={(event) =>
                                    updateResponsibility(index, {
                                        content: event.target.value,
                                    })
                                }
                                aria-invalid={Boolean(
                                    form.errors[
                                        `responsibilities.${index}.content`
                                    ],
                                )}
                            />
                            <InputError
                                message={
                                    form.errors[
                                        `responsibilities.${index}.content`
                                    ]
                                }
                            />
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor={`responsibility-${index}-priority`}>
                                Priority
                            </Label>
                            <select
                                id={`responsibility-${index}-priority`}
                                className={selectClassName}
                                value={row.priority}
                                onChange={(event) =>
                                    updateResponsibility(index, {
                                        priority: event.target
                                            .value as Priority,
                                    })
                                }
                            >
                                {options.priorities.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <RowControls
                            kind="responsibilities"
                            index={index}
                            count={form.data.responsibilities.length}
                            onMove={(direction) =>
                                moveRow('responsibilities', index, direction)
                            }
                            onRemove={() => removeResponsibility(index)}
                        />
                    </div>
                ))}
                <Button
                    id="add-responsibility"
                    type="button"
                    variant="outline"
                    disabled={form.data.responsibilities.length >= 20}
                    onClick={() => {
                        const index = form.data.responsibilities.length;
                        form.setData('responsibilities', [
                            ...form.data.responsibilities,
                            emptyResponsibility(),
                        ]);
                        focusAfterRender(`responsibility-${index}-content`);
                    }}
                >
                    <Plus /> Add responsibility
                </Button>
            </section>

            <section
                className="min-w-0 space-y-4"
                aria-labelledby="competencies-heading"
            >
                <div>
                    <h2
                        id="competencies-heading"
                        className="text-xl font-semibold"
                    >
                        Competencies
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Capture the skills, behaviors, and knowledge expected.
                    </p>
                </div>
                <InputError message={form.errors.competencies} />
                {form.data.competencies.map((row, index) => (
                    <div
                        key={row.id ?? `competency-${index}`}
                        className="grid min-w-0 gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_10rem_auto]"
                    >
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor={`competency-${index}-name`}>
                                Competency {index + 1}
                            </Label>
                            <Input
                                id={`competency-${index}-name`}
                                value={row.name}
                                onChange={(event) =>
                                    updateCompetency(index, {
                                        name: event.target.value,
                                    })
                                }
                                aria-invalid={Boolean(
                                    form.errors[`competencies.${index}.name`],
                                )}
                            />
                            <InputError
                                message={
                                    form.errors[`competencies.${index}.name`]
                                }
                            />
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor={`competency-${index}-category`}>
                                Category
                            </Label>
                            <select
                                id={`competency-${index}-category`}
                                className={selectClassName}
                                value={row.category}
                                onChange={(event) =>
                                    updateCompetency(index, {
                                        category: event.target
                                            .value as CompetencyCategory,
                                    })
                                }
                            >
                                {options.competencyCategories.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor={`competency-${index}-priority`}>
                                Priority
                            </Label>
                            <select
                                id={`competency-${index}-priority`}
                                className={selectClassName}
                                value={row.priority}
                                onChange={(event) =>
                                    updateCompetency(index, {
                                        priority: event.target
                                            .value as Priority,
                                    })
                                }
                            >
                                {options.priorities.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <RowControls
                            kind="competencies"
                            index={index}
                            count={form.data.competencies.length}
                            onMove={(direction) =>
                                moveRow('competencies', index, direction)
                            }
                            onRemove={() => removeCompetency(index)}
                        />
                        <div className="grid min-w-0 gap-2 sm:col-span-2 lg:col-span-3">
                            <Label htmlFor={`competency-${index}-evidence`}>
                                Supporting evidence (optional)
                            </Label>
                            <Textarea
                                id={`competency-${index}-evidence`}
                                className="min-h-16"
                                value={row.source_evidence}
                                onChange={(event) =>
                                    updateCompetency(index, {
                                        source_evidence: event.target.value,
                                    })
                                }
                                aria-invalid={Boolean(
                                    form.errors[
                                        `competencies.${index}.source_evidence`
                                    ],
                                )}
                            />
                            <InputError
                                message={
                                    form.errors[
                                        `competencies.${index}.source_evidence`
                                    ]
                                }
                            />
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor={`competency-${index}-confidence`}>
                                Your confidence (1–5)
                            </Label>
                            <Input
                                id={`competency-${index}-confidence`}
                                type="number"
                                min="1"
                                max="5"
                                value={row.user_confidence}
                                onChange={(event) =>
                                    updateCompetency(index, {
                                        user_confidence: event.target.value,
                                    })
                                }
                                aria-invalid={Boolean(
                                    form.errors[
                                        `competencies.${index}.user_confidence`
                                    ],
                                )}
                            />
                            <InputError
                                message={
                                    form.errors[
                                        `competencies.${index}.user_confidence`
                                    ]
                                }
                            />
                        </div>
                    </div>
                ))}
                <Button
                    id="add-competency"
                    type="button"
                    variant="outline"
                    disabled={form.data.competencies.length >= 30}
                    onClick={() => {
                        const index = form.data.competencies.length;
                        form.setData('competencies', [
                            ...form.data.competencies,
                            emptyCompetency(),
                        ]);
                        focusAfterRender(`competency-${index}-name`);
                    }}
                >
                    <Plus /> Add competency
                </Button>
            </section>

            <div className="flex flex-wrap items-center gap-3 border-t pt-6">
                <Button type="submit" disabled={form.processing}>
                    {form.processing
                        ? 'Saving…'
                        : targetRole
                          ? 'Save changes'
                          : 'Save draft'}
                </Button>
                <p className="text-sm text-muted-foreground">
                    You can review and confirm this profile after saving.
                </p>
            </div>
        </form>
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
        <div
            className="flex items-end gap-1 sm:justify-end"
            aria-label={`Reorder ${label} ${index + 1}`}
        >
            <Button
                id={`${kind}-${index}-move--1`}
                type="button"
                size="icon"
                variant="outline"
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
                variant="outline"
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
