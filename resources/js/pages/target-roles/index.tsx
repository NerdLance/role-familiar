import { Head, Link } from '@inertiajs/react';
import { BriefcaseBusiness, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create, index, show } from '@/routes/target-roles';
import type { TargetRoleSummary } from '@/types';

interface RoleListItem extends TargetRoleSummary {
    responsibilities_count: number;
    competencies_count: number;
}

export default function TargetRoleIndex({
    targetRoles,
}: {
    targetRoles: RoleListItem[];
}) {
    return (
        <>
            <Head title="Target roles" />
            <main className="mx-auto w-full max-w-6xl min-w-0 space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Target roles
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            Draft, confirm, and choose the role your Queue Wyrm
                            is coaching you toward.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> Create role
                        </Link>
                    </Button>
                </div>

                {targetRoles.length === 0 ? (
                    <section className="grid min-h-64 place-items-center rounded-xl border border-dashed p-6 text-center">
                        <div className="max-w-md space-y-3">
                            <BriefcaseBusiness className="mx-auto size-10 text-muted-foreground" />
                            <h2 className="text-lg font-semibold">
                                No target roles yet
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Create a private draft, add the responsibilities
                                and competencies that matter, then confirm it
                                when it feels accurate.
                            </p>
                            <Button asChild>
                                <Link href={create()}>
                                    Create your first role
                                </Link>
                            </Button>
                        </div>
                    </section>
                ) : (
                    <div className="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {targetRoles.map((role) => (
                            <Card key={role.public_id} className="min-w-0">
                                <CardHeader className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={
                                                role.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {role.is_active
                                                ? 'Active'
                                                : role.status.replace('_', ' ')}
                                        </Badge>
                                        {role.company_name && (
                                            <span className="truncate text-sm text-muted-foreground">
                                                {role.company_name}
                                            </span>
                                        )}
                                    </div>
                                    <CardTitle className="break-words">
                                        {role.title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm text-muted-foreground">
                                    {role.responsibilities_count}{' '}
                                    responsibilities · {role.competencies_count}{' '}
                                    competencies
                                </CardContent>
                                <CardFooter>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link href={show(role)}>
                                            Review role
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}

TargetRoleIndex.layout = {
    breadcrumbs: [{ title: 'Target roles', href: index() }],
};
