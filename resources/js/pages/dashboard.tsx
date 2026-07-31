import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <section className="rounded-xl border bg-card p-6 shadow-sm">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Role Familiar dashboard
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Your workspace is ready. Product features will appear
                        here.
                    </p>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
