import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Keyboard, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { create as createJobListing } from '@/actions/App/Http/Controllers/JobListingTargetRoleController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TargetRoleComposer } from '@/features/target-roles/components/target-role-composer';
import { index } from '@/routes/target-roles';
import type { TargetRoleOptions } from '@/types';

export default function CreateTargetRole({
    options,
}: {
    options: TargetRoleOptions;
}) {
    const [showManualForm, setShowManualForm] = useState(false);

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
                            Choose how you want to give Queue Wyrm the role
                            context.
                        </p>
                    </div>
                </div>

                {!showManualForm && (
                    <section
                        className="grid min-w-0 gap-4 sm:grid-cols-2"
                        aria-label="Role creation method"
                    >
                        <Card className="min-w-0">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText /> Paste a job listing
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    Queue Wyrm will prepare a private proposal
                                    for you to review and correct.
                                </p>
                                <Button asChild className="w-full">
                                    <Link href={createJobListing()}>
                                        <Sparkles /> Paste a job listing
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                        <Card className="min-w-0">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Keyboard /> Enter manually
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    Build the role profile yourself without
                                    requesting AI analysis.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => setShowManualForm(true)}
                                >
                                    Create manually
                                </Button>
                            </CardContent>
                        </Card>
                    </section>
                )}

                {showManualForm && (
                    <section
                        className="space-y-4"
                        aria-labelledby="manual-role-heading"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2
                                    id="manual-role-heading"
                                    className="text-xl font-semibold"
                                >
                                    Manual role profile
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Saving creates a private draft.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setShowManualForm(false)}
                            >
                                Change method
                            </Button>
                        </div>
                        <TargetRoleComposer options={options} />
                    </section>
                )}
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
