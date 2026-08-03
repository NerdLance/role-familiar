import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TargetRoleComposer } from '@/features/target-roles/components/target-role-composer';
import { index, show } from '@/routes/target-roles';
import type { TargetRoleOptions, TargetRoleProfile } from '@/types';

export default function EditTargetRole({
    targetRole,
    options,
}: {
    targetRole: TargetRoleProfile;
    options: TargetRoleOptions;
}) {
    return (
        <>
            <Head title={`Edit ${targetRole.title ?? 'target role'}`} />
            <main className="mx-auto w-full max-w-5xl min-w-0 space-y-6 p-4 sm:p-6">
                <div className="space-y-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={show(targetRole)}>
                            <ArrowLeft /> Review role
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight break-words">
                            Edit {targetRole.title ?? 'target role'}
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            Changes to a confirmed role remain part of its
                            confirmed coaching profile.
                        </p>
                    </div>
                </div>
                <TargetRoleComposer targetRole={targetRole} options={options} />
            </main>
        </>
    );
}

EditTargetRole.layout = {
    breadcrumbs: [
        { title: 'Target roles', href: index() },
        { title: 'Edit role', href: '#' },
    ],
};
