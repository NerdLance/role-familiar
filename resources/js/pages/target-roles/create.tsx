import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TargetRoleComposer } from '@/features/target-roles/components/target-role-composer';
import { index } from '@/routes/target-roles';
import type { TargetRoleOptions } from '@/types';

export default function CreateTargetRole({
    options,
}: {
    options: TargetRoleOptions;
}) {
    return (
        <>
            <Head title="Create target role" />
            <main className="mx-auto w-full max-w-5xl min-w-0 space-y-6 p-4 sm:p-6">
                <div className="space-y-3">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={index()}>
                            <ArrowLeft /> Target roles
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Create a target role
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            Start with what you know. Saving creates a private
                            draft that only you can review.
                        </p>
                    </div>
                </div>
                <TargetRoleComposer options={options} />
            </main>
        </>
    );
}

CreateTargetRole.layout = {
    breadcrumbs: [
        { title: 'Target roles', href: index() },
        { title: 'Create role', href: '#' },
    ],
};
