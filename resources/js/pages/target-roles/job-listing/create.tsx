import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/JobListingTargetRoleController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { QueueWyrmRoleStatus } from '@/features/target-roles/components/queue-wyrm-role-status';
import { create } from '@/routes/target-roles';
import type { QueueWyrmRoleVisual } from '@/types';

interface ListingForm {
    job_listing: string;
    interview_at: string;
    biggest_concern: string;
    company_name: string;
}

export default function CreateJobListingRole({
    queueWyrm,
}: {
    queueWyrm: QueueWyrmRoleVisual;
}) {
    const form = useForm<ListingForm>({
        job_listing: '',
        interview_at: '',
        biggest_concern: '',
        company_name: '',
    });
    const [isListening, setIsListening] = useState(false);
    const isSubmitting = useRef(false);

    useEffect(() => {
        const removeBeforeVisit = router.on('before', (event) => {
            if (
                form.isDirty &&
                !isSubmitting.current &&
                !window.confirm('Leave without saving this job listing?')
            ) {
                event.preventDefault();
            }
        });
        const beforeUnload = (event: BeforeUnloadEvent) => {
            if (form.isDirty) {
                event.preventDefault();
            }
        };
        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            removeBeforeVisit();
            window.removeEventListener('beforeunload', beforeUnload);
        };
    }, [form.isDirty]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        isSubmitting.current = true;
        form.post(store().url, {
            onFinish: () => {
                isSubmitting.current = false;
            },
        });
    }

    return (
        <>
            <Head title="Paste a job listing" />
            <main className="mx-auto grid w-full max-w-6xl min-w-0 gap-6 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div className="min-w-0 space-y-6">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={create()}>
                            <ArrowLeft /> Creation options
                        </Link>
                    </Button>
                    <header>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Paste a job listing
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            The listing stays private. Analysis is only a
                            proposal until you review and confirm it.
                        </p>
                    </header>
                    <form className="min-w-0 space-y-6" onSubmit={submit}>
                        <section className="min-w-0 space-y-2 rounded-xl border bg-card p-4 sm:p-6">
                            <Label htmlFor="job_listing">Job listing</Label>
                            <Textarea
                                id="job_listing"
                                autoFocus
                                className="min-h-72 resize-y"
                                value={form.data.job_listing}
                                onFocus={() => setIsListening(true)}
                                onBlur={() => setIsListening(false)}
                                onChange={(event) =>
                                    form.setData(
                                        'job_listing',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.job_listing)}
                                aria-describedby="job-listing-help job-listing-count"
                            />
                            <div
                                id="job-listing-help"
                                className="text-sm text-muted-foreground"
                            >
                                Paste 100–50,000 characters. Instructions inside
                                the listing are treated as source text, never
                                commands.
                            </div>
                            <div
                                id="job-listing-count"
                                className="text-right text-xs text-muted-foreground"
                            >
                                {form.data.job_listing.length.toLocaleString()}{' '}
                                / 50,000
                            </div>
                            <InputError message={form.errors.job_listing} />
                        </section>
                        <section
                            className="grid min-w-0 gap-5 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6"
                            aria-labelledby="known-context-heading"
                        >
                            <div className="sm:col-span-2">
                                <h2
                                    id="known-context-heading"
                                    className="text-lg font-semibold"
                                >
                                    What you already know{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Your corrections remain authoritative over
                                    the proposal.
                                </p>
                            </div>
                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="company_name">
                                    Company name correction
                                </Label>
                                <Input
                                    id="company_name"
                                    value={form.data.company_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'company_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.company_name}
                                />
                            </div>
                            <div className="grid min-w-0 gap-2">
                                <Label htmlFor="interview_at">
                                    Interview date and time
                                </Label>
                                <Input
                                    id="interview_at"
                                    type="datetime-local"
                                    value={form.data.interview_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'interview_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.interview_at}
                                />
                            </div>
                            <div className="grid min-w-0 gap-2 sm:col-span-2">
                                <Label htmlFor="biggest_concern">
                                    Biggest concern
                                </Label>
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
                                <InputError
                                    message={form.errors.biggest_concern}
                                />
                            </div>
                        </section>
                        <Button type="submit" disabled={form.processing}>
                            <Sparkles />{' '}
                            {form.processing
                                ? 'Requesting analysis…'
                                : 'Analyze listing'}
                        </Button>
                    </form>
                </div>
                <div className="lg:sticky lg:top-6 lg:self-start">
                    <QueueWyrmRoleStatus
                        visual={queueWyrm}
                        mode={
                            form.processing
                                ? 'thinking'
                                : isListening
                                  ? 'listening'
                                  : 'idle'
                        }
                        announcement={
                            form.processing
                                ? 'Queue Wyrm is preparing your request.'
                                : isListening
                                  ? 'Queue Wyrm is listening while you paste.'
                                  : 'Queue Wyrm is ready when you are.'
                        }
                    />
                </div>
            </main>
        </>
    );
}

CreateJobListingRole.layout = {
    breadcrumbs: [
        { title: 'Target roles', href: create() },
        { title: 'Paste listing', href: '#' },
    ],
};
