import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    CheckCircle2,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    archive,
    confirm,
    destroy,
    edit,
    index,
    restore,
} from '@/routes/target-roles';
import type { TargetRoleProfile } from '@/types';

export default function ShowTargetRole({
    targetRole,
}: {
    targetRole: TargetRoleProfile;
}) {
    const confirmation = useForm<{
        title?: string;
        responsibilities?: string;
        competencies?: string;
    }>({});

    function deleteRole() {
        if (
            !window.confirm(
                `Delete “${targetRole.title}”? This moves it to recoverable soft-deleted storage.`,
            )
        ) {
            return;
        }

        router.delete(destroy(targetRole).url, {
            data: { confirmation: true },
        });
    }

    return (
        <>
            <Head title={targetRole.title} />
            <main className="mx-auto w-full max-w-5xl min-w-0 space-y-6 p-4 sm:p-6">
                <div className="space-y-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={index()}>
                            <ArrowLeft /> Target roles
                        </Link>
                    </Button>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <div className="mb-2 flex flex-wrap items-center gap-2">
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
                            <h1 className="text-2xl font-semibold tracking-tight break-words">
                                {targetRole.title}
                            </h1>
                            {targetRole.role_summary && (
                                <p className="mt-2 max-w-3xl whitespace-pre-wrap text-muted-foreground">
                                    {targetRole.role_summary}
                                </p>
                            )}
                        </div>
                        {!targetRole.archived_at && (
                            <Button asChild variant="outline">
                                <Link href={edit(targetRole)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid min-w-0 gap-5 lg:grid-cols-2">
                    <RoleList
                        title="Responsibilities"
                        empty="No responsibilities yet."
                        rows={targetRole.responsibilities.map((row) => ({
                            key: row.id ?? row.content,
                            title: row.content,
                            meta: row.priority,
                        }))}
                    />
                    <RoleList
                        title="Competencies"
                        empty="No competencies yet."
                        rows={targetRole.competencies.map((row) => ({
                            key: row.id ?? row.name,
                            title: row.name,
                            meta: `${row.category} · ${row.priority}${row.user_confidence ? ` · confidence ${row.user_confidence}/5` : ''}`,
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
                        <CardContent className="grid gap-4 text-sm sm:grid-cols-3">
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
                        </CardContent>
                    </Card>
                )}

                <InputError
                    message={
                        confirmation.errors.title ??
                        confirmation.errors.responsibilities ??
                        confirmation.errors.competencies
                    }
                />
                <section
                    className="flex flex-wrap gap-3 border-t pt-6"
                    aria-label="Role actions"
                >
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
            </main>
        </>
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
                                                {row.detail}
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
