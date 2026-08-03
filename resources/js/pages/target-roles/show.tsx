import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    CheckCircle2,
    Pencil,
    RefreshCw,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import RetryJobListingAnalysisController from '@/actions/App/Http/Controllers/RetryJobListingAnalysisController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { JobListingReviewForm } from '@/features/target-roles/components/job-listing-review-form';
import { QueueWyrmRoleStatus } from '@/features/target-roles/components/queue-wyrm-role-status';
import {
    archive,
    confirm,
    destroy,
    edit,
    index,
    restore,
} from '@/routes/target-roles';
import type {
    QueueWyrmRoleVisual,
    RoleAnalysisView,
    TargetRoleOptions,
    TargetRoleProfile,
} from '@/types';

interface Props {
    targetRole: TargetRoleProfile;
    analysis: RoleAnalysisView | null;
    options: TargetRoleOptions;
    queueWyrm: QueueWyrmRoleVisual;
    celebrateConfirmation: boolean;
}

export default function ShowTargetRole(props: Props) {
    const { targetRole, analysis, options, queueWyrm, celebrateConfirmation } =
        props;
    const isPending =
        targetRole.status === 'analysis_pending' &&
        (analysis?.status === 'pending' || analysis?.status === 'processing');
    const previousStatus = useRef(analysis?.status);
    const { start, stop } = usePoll(
        2500,
        { only: ['targetRole', 'analysis'] },
        { autoStart: false, keepAlive: false, mode: 'rest' },
    );

    useEffect(() => {
        const updatePolling = () => {
            if (isPending && document.visibilityState === 'visible') {
                start();
            } else {
                stop();
            }
        };
        updatePolling();
        document.addEventListener('visibilitychange', updatePolling);

        return () => {
            document.removeEventListener('visibilitychange', updatePolling);
            stop();
        };
    }, [isPending, start, stop]);

    useEffect(() => {
        if (
            previousStatus.current &&
            previousStatus.current !== analysis?.status
        ) {
            document.getElementById('analysis-status-heading')?.focus();
        }

        previousStatus.current = analysis?.status;
    }, [analysis?.status]);

    if (isPending) {
        return (
            <AnalysisPending
                targetRole={targetRole}
                analysis={analysis}
                queueWyrm={queueWyrm}
            />
        );
    }

    if (
        targetRole.status === 'analysis_failed' ||
        analysis?.status === 'failed'
    ) {
        return (
            <AnalysisFailed
                targetRole={targetRole}
                analysis={analysis}
                queueWyrm={queueWyrm}
            />
        );
    }

    if (
        targetRole.status === 'review_required' &&
        analysis?.status === 'completed' &&
        analysis.proposal
    ) {
        return (
            <AnalysisReview
                targetRole={targetRole}
                analysis={analysis}
                options={options}
                queueWyrm={queueWyrm}
            />
        );
    }

    return (
        <RoleProfile
            targetRole={targetRole}
            queueWyrm={queueWyrm}
            celebrating={celebrateConfirmation}
        />
    );
}

function PageFrame({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <main className="mx-auto w-full max-w-6xl min-w-0 space-y-6 p-4 sm:p-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href={index()}>
                        <ArrowLeft /> Target roles
                    </Link>
                </Button>
                {children}
            </main>
        </>
    );
}

function AnalysisPending({
    targetRole,
    analysis,
    queueWyrm,
}: Pick<Props, 'targetRole' | 'analysis' | 'queueWyrm'>) {
    return (
        <PageFrame title="Analyzing job listing">
            <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section
                    className="space-y-5 rounded-xl border bg-card p-5 sm:p-8"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <div className="space-y-2">
                        <Badge>Analysis in progress</Badge>
                        <h1
                            id="analysis-status-heading"
                            tabIndex={-1}
                            className="text-2xl font-semibold"
                        >
                            Queue Wyrm is reading the listing
                        </h1>
                        <p className="text-muted-foreground">
                            You can leave this page. The queued analysis will
                            keep running, and this page will stop polling as
                            soon as it completes or fails.
                        </p>
                    </div>
                    <div className="space-y-3" aria-hidden="true">
                        <div className="h-5 w-3/4 animate-pulse rounded bg-muted motion-reduce:animate-none" />
                        <div className="h-5 w-1/2 animate-pulse rounded bg-muted motion-reduce:animate-none" />
                        <div className="h-24 animate-pulse rounded bg-muted motion-reduce:animate-none" />
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.reload({
                                    only: ['targetRole', 'analysis'],
                                })
                            }
                        >
                            <RefreshCw /> Refresh status
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={index()}>Return to target roles</Link>
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Attempt status: {analysis?.status ?? targetRole.status}
                    </p>
                </section>
                <QueueWyrmRoleStatus
                    visual={queueWyrm}
                    mode="thinking"
                    announcement="Queue Wyrm is thinking through the role details."
                />
            </div>
        </PageFrame>
    );
}

function AnalysisFailed({
    targetRole,
    analysis,
    queueWyrm,
}: Pick<Props, 'targetRole' | 'analysis' | 'queueWyrm'>) {
    const retry = useForm({});

    return (
        <PageFrame title="Analysis needs another try">
            <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section
                    className="space-y-5 rounded-xl border border-destructive/30 bg-card p-5 sm:p-8"
                    role="alert"
                >
                    <Badge variant="destructive">Analysis failed</Badge>
                    <h1
                        id="analysis-status-heading"
                        tabIndex={-1}
                        className="text-2xl font-semibold"
                    >
                        The proposal could not be prepared
                    </h1>
                    <p className="text-muted-foreground">
                        {analysis?.failure_message ??
                            'The analysis could not be completed.'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Your original listing remains private. Retrying creates
                        a new analysis attempt and preserves this failure
                        record.
                    </p>
                    <InputError
                        message={
                            (retry.errors as Record<string, string>).analysis
                        }
                    />
                    <Button
                        disabled={retry.processing}
                        onClick={() =>
                            retry.post(
                                RetryJobListingAnalysisController(targetRole)
                                    .url,
                            )
                        }
                    >
                        <RefreshCw />{' '}
                        {retry.processing ? 'Retrying…' : 'Retry analysis'}
                    </Button>
                </section>
                <QueueWyrmRoleStatus
                    visual={queueWyrm}
                    mode="idle"
                    announcement="Queue Wyrm is ready to try again."
                />
            </div>
        </PageFrame>
    );
}

function AnalysisReview({
    targetRole,
    analysis,
    options,
    queueWyrm,
}: {
    targetRole: TargetRoleProfile;
    analysis: RoleAnalysisView;
    options: TargetRoleOptions;
    queueWyrm: QueueWyrmRoleVisual;
}) {
    const confirmation = useForm<{ acknowledge_uncertainty: boolean }>({
        acknowledge_uncertainty: false,
    });
    const hasUncertainty = Boolean(
        analysis.proposal?.assumptions.length ||
        analysis.proposal?.ambiguities.length,
    );

    const confirmationErrors = confirmation.errors as Record<string, string>;

    return (
        <PageFrame title="Review analysis proposal">
            <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <header className="min-w-0">
                    <Badge>Review required</Badge>
                    <h1
                        id="analysis-status-heading"
                        tabIndex={-1}
                        className="mt-3 text-2xl font-semibold tracking-tight"
                    >
                        Review and correct the role proposal
                    </h1>
                    <p className="mt-2 max-w-3xl text-muted-foreground">
                        Evidence, confidence, ambiguities, and assumptions
                        remain visible so you can decide what belongs in your
                        profile.
                    </p>
                </header>
                <QueueWyrmRoleStatus
                    visual={queueWyrm}
                    mode="idle"
                    announcement="Queue Wyrm is waiting while you review the proposal."
                />
            </div>
            <JobListingReviewForm
                targetRole={targetRole}
                analysis={analysis}
                options={options}
            />
            <Card className="border-primary/30">
                <CardHeader>
                    <CardTitle>Confirm this role profile</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Saving review corrections does not confirm the role.
                        Confirmation makes this user-reviewed profile the active
                        coaching context and archives any previously active
                        role.
                    </p>
                    {hasUncertainty && (
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="acknowledge_uncertainty"
                                checked={
                                    confirmation.data.acknowledge_uncertainty
                                }
                                onCheckedChange={(checked) =>
                                    confirmation.setData(
                                        'acknowledge_uncertainty',
                                        checked === true,
                                    )
                                }
                            />
                            <Label
                                htmlFor="acknowledge_uncertainty"
                                className="leading-5"
                            >
                                I reviewed the visible assumptions and
                                ambiguities, including anything I left
                                unresolved.
                            </Label>
                        </div>
                    )}
                    <InputError
                        message={
                            confirmationErrors.acknowledge_uncertainty ??
                            confirmationErrors.analysis ??
                            confirmationErrors.title ??
                            confirmationErrors.role_summary ??
                            confirmationErrors.responsibilities ??
                            confirmationErrors.competencies
                        }
                    />
                    <Button
                        disabled={
                            !analysis.review ||
                            confirmation.processing ||
                            (hasUncertainty &&
                                !confirmation.data.acknowledge_uncertainty)
                        }
                        onClick={() =>
                            confirmation.post(confirm(targetRole).url)
                        }
                    >
                        <CheckCircle2 /> Confirm and make active
                    </Button>
                    {!analysis.review && (
                        <p className="text-sm text-muted-foreground">
                            Save the review corrections before confirming.
                        </p>
                    )}
                </CardContent>
            </Card>
        </PageFrame>
    );
}

function RoleProfile({
    targetRole,
    queueWyrm,
    celebrating,
}: {
    targetRole: TargetRoleProfile;
    queueWyrm: QueueWyrmRoleVisual;
    celebrating: boolean;
}) {
    const confirmation = useForm<{
        title?: string;
        role_summary?: string;
        responsibilities?: string;
        competencies?: string;
    }>({});
    const title = targetRole.title ?? 'Untitled target role';
    function deleteRole() {
        if (
            window.confirm(
                `Delete “${title}”? This moves it to recoverable soft-deleted storage.`,
            )
        ) {
            router.delete(destroy(targetRole).url, {
                data: { confirmation: true },
            });
        }
    }

    return (
        <PageFrame title={title}>
            <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <header className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge>
                            {targetRole.is_active
                                ? 'Active coaching role'
                                : targetRole.status.replace('_', ' ')}
                        </Badge>
                        {targetRole.company_name && (
                            <span className="text-sm text-muted-foreground">
                                {targetRole.company_name}
                            </span>
                        )}
                    </div>
                    <h1 className="mt-3 text-2xl font-semibold break-words">
                        {title}
                    </h1>
                    {targetRole.role_summary && (
                        <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                            {targetRole.role_summary}
                        </p>
                    )}
                </header>
                <QueueWyrmRoleStatus
                    visual={queueWyrm}
                    mode={celebrating ? 'celebrating' : 'idle'}
                    announcement={
                        celebrating
                            ? 'Role confirmed. Queue Wyrm is celebrating with you.'
                            : 'Queue Wyrm is idling with your reviewed role context.'
                    }
                />
            </div>
            <div className="grid min-w-0 gap-5 lg:grid-cols-2">
                <RoleList
                    title="Responsibilities"
                    empty="No responsibilities yet."
                    rows={targetRole.responsibilities.map((row) => ({
                        key: row.id ?? row.content,
                        title: row.content,
                        meta: row.priority,
                        detail: row.source_evidence,
                    }))}
                />
                <RoleList
                    title="Competencies"
                    empty="No competencies yet."
                    rows={targetRole.competencies.map((row) => ({
                        key: row.id ?? row.name,
                        title: row.name,
                        meta: `${row.category} · ${row.priority}${row.user_confidence ? ` · your confidence ${row.user_confidence}/5` : ''}`,
                        detail: row.source_evidence,
                    }))}
                />
            </div>
            {(targetRole.interview_at ||
                targetRole.biggest_concern ||
                targetRole.seniority) && (
                <Card>
                    <CardHeader>
                        <CardTitle>Role details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-3">
                            {targetRole.seniority && (
                                <Detail
                                    label="Seniority"
                                    value={targetRole.seniority}
                                />
                            )}
                            {targetRole.interview_at && (
                                <Detail
                                    label="Interview"
                                    value={new Date(
                                        targetRole.interview_at,
                                    ).toLocaleString()}
                                />
                            )}
                            {targetRole.biggest_concern && (
                                <Detail
                                    label="Biggest concern"
                                    value={targetRole.biggest_concern}
                                />
                            )}
                        </dl>
                    </CardContent>
                </Card>
            )}
            <InputError
                message={
                    confirmation.errors.title ??
                    confirmation.errors.role_summary ??
                    confirmation.errors.responsibilities ??
                    confirmation.errors.competencies
                }
            />
            <section
                className="flex flex-wrap gap-3 border-t pt-6"
                aria-label="Role actions"
            >
                {!targetRole.archived_at && (
                    <Button asChild variant="outline">
                        <Link href={edit(targetRole)}>
                            <Pencil /> Edit
                        </Link>
                    </Button>
                )}
                {!targetRole.is_active && !targetRole.archived_at && (
                    <Button
                        disabled={confirmation.processing}
                        onClick={() =>
                            confirmation.post(confirm(targetRole).url)
                        }
                    >
                        <CheckCircle2 /> Confirm and make active
                    </Button>
                )}
                {targetRole.is_active && (
                    <Button
                        variant="outline"
                        onClick={() => router.post(archive(targetRole).url)}
                    >
                        <Archive /> Archive role
                    </Button>
                )}
                {targetRole.archived_at && (
                    <Button
                        onClick={() => router.post(restore(targetRole).url)}
                    >
                        <RotateCcw /> Restore and make active
                    </Button>
                )}
                {!targetRole.is_active && (
                    <Button variant="destructive" onClick={deleteRole}>
                        <Trash2 /> Delete role
                    </Button>
                )}
            </section>
        </PageFrame>
    );
}

function RoleList({
    title,
    empty,
    rows,
}: {
    title: string;
    empty: string;
    rows: Array<{
        key: string | number;
        title: string;
        meta: string;
        detail?: string | null;
    }>;
}) {
    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length ? (
                    <ol className="space-y-3">
                        {rows.map((row, index) => (
                            <li
                                key={row.key}
                                className="min-w-0 rounded-lg border p-3"
                            >
                                <div className="flex min-w-0 gap-3">
                                    <span className="font-mono text-sm text-muted-foreground">
                                        {index + 1}.
                                    </span>
                                    <div className="min-w-0">
                                        <p className="font-medium break-words">
                                            {row.title}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground capitalize">
                                            {row.meta.replaceAll('_', ' ')}
                                        </p>
                                        {row.detail && (
                                            <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                                <strong>
                                                    Source evidence:
                                                </strong>{' '}
                                                “{row.detail}”
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                )}
            </CardContent>
        </Card>
    );
}
function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="font-medium">{label}</dt>
            <dd className="mt-1 break-words whitespace-pre-wrap text-muted-foreground">
                {value}
            </dd>
        </div>
    );
}

ShowTargetRole.layout = {
    breadcrumbs: [
        { title: 'Target roles', href: index() },
        { title: 'Review role', href: '#' },
    ],
};
