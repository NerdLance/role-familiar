import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Role Familiar" />
            <main className="flex min-h-screen items-center justify-center bg-background p-6 text-foreground">
                <section className="w-full max-w-2xl rounded-xl border bg-card p-8 shadow-sm sm:p-12">
                    <p className="text-sm font-medium text-muted-foreground">
                        Role Familiar
                    </p>
                    <h1 className="mt-3 text-4xl font-semibold tracking-tight">
                        Make your next role feel familiar.
                    </h1>
                    <p className="mt-4 max-w-xl text-lg text-muted-foreground">
                        A thoughtful workspace for exploring career
                        opportunities. More is on its way.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                            >
                                Open dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={register()}
                                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                                >
                                    Create an account
                                </Link>
                                <Link
                                    href={login()}
                                    className="rounded-md border px-4 py-2 text-sm font-medium"
                                >
                                    Log in
                                </Link>
                            </>
                        )}
                    </div>
                </section>
            </main>
        </>
    );
}
