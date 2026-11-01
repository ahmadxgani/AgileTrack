import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarRange,
    ClipboardCheck,
    FolderKanban,
    KeyRound,
    ListChecks,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import BoardPreview from '@/components/marketing/board-preview';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const features = [
    {
        icon: FolderKanban,
        title: 'Projects with clear ownership',
        description:
            'Keep each initiative in its own space with a status, a timeline and the members who are allowed to work on it.',
    },
    {
        icon: ListChecks,
        title: 'A backlog that stays honest',
        description:
            'Capture work as it comes up, rank it by priority and assign owners, so planning starts from one shared list.',
    },
    {
        icon: CalendarRange,
        title: 'Sprints and a live board',
        description:
            'Plan a sprint from the backlog and move cards across the board. Every status change is recorded with who made it and when.',
    },
    {
        icon: ClipboardCheck,
        title: 'Reviews with decisions',
        description:
            'Schedule the sprint review, write down the feedback and record whether the result was accepted or sent back to the backlog.',
    },
    {
        icon: Users,
        title: 'Roles that match the team',
        description:
            'Owners, product owners, managers, analysts, members and QA each get the access they need and nothing more.',
    },
    {
        icon: ShieldCheck,
        title: 'Secure by default',
        description:
            'Sign in with passkeys or two-factor codes. Accounts are created by an administrator, never by an open sign-up form.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Set up the project',
        description:
            'Create the project, invite the team and give everyone a role.',
    },
    {
        number: '02',
        title: 'Fill the backlog',
        description:
            'Add work items with a priority and an owner, then rank what matters.',
    },
    {
        number: '03',
        title: 'Run the sprint',
        description:
            'Pull items onto the board and move them from to do to done.',
    },
    {
        number: '04',
        title: 'Review and decide',
        description:
            'Walk through the result, record the decision and feed the follow-ups back.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;

    const primaryAction = auth.user ? (
        <Button size="lg" asChild>
            <Link href={dashboard()}>
                Open dashboard
                <ArrowRight />
            </Link>
        </Button>
    ) : (
        <Button size="lg" asChild>
            <Link href={login()}>
                Log in
                <ArrowRight />
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Agile project tracking for small teams" />

            <div className="relative isolate flex min-h-svh flex-col overflow-x-clip bg-background text-foreground">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[42rem] bg-[radial-gradient(60rem_28rem_at_50%_-6rem,var(--color-primary)/18%,transparent)] dark:bg-[radial-gradient(60rem_28rem_at_50%_-6rem,var(--color-primary)/22%,transparent)]"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(to_right,var(--color-border)_1px,transparent_1px),linear-gradient(to_bottom,var(--color-border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_top,black_20%,transparent_70%)] bg-[size:4rem_4rem] opacity-40"
                />

                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
                    <Link href="/" className="rounded-lg">
                        <AppLogo size="sm" />
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button variant="ghost" asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <Button variant="outline" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                        )}
                    </nav>
                </header>

                <main className="flex-1">
                    <section className="mx-auto grid w-full max-w-6xl items-center gap-12 px-6 pt-12 pb-20 lg:grid-cols-[1.05fr_1fr] lg:pt-20 lg:pb-28">
                        <div className="max-w-xl">
                            <span className="inline-flex items-center gap-2 rounded-full border bg-card px-3 py-1 text-xs font-medium text-muted-foreground shadow-xs">
                                <span className="size-1.5 rounded-full bg-brand-accent" />
                                Built for small, cross-functional teams
                            </span>
                            <h1 className="mt-6 font-display text-4xl font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                Plan the sprint.
                                <br />
                                <span className="text-primary">
                                    Track the work.
                                </span>
                                <br />
                                Review the result.
                            </h1>
                            <p className="mt-6 text-lg text-pretty text-muted-foreground">
                                {name} brings projects, backlog, sprint boards
                                and sprint reviews into one calm workspace, so
                                the team always knows what is next and what was
                                decided.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                {primaryAction}
                                <Button variant="ghost" size="lg" asChild>
                                    <a href="#how-it-works">See how it works</a>
                                </Button>
                            </div>
                            <dl className="mt-10 grid max-w-md grid-cols-3 gap-6 border-t pt-6">
                                {[
                                    ['Backlog', 'ranked by priority'],
                                    ['Board', 'with a full history'],
                                    ['Reviews', 'with a decision'],
                                ].map(([term, detail]) => (
                                    <div key={term}>
                                        <dt className="font-display text-sm font-bold">
                                            {term}
                                        </dt>
                                        <dd className="text-xs text-muted-foreground">
                                            {detail}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <div className="relative isolate">
                            <div
                                aria-hidden="true"
                                className="absolute -inset-6 -z-10 rounded-[2rem] bg-gradient-to-br from-primary/20 via-transparent to-brand-accent/20 blur-2xl"
                            />
                            <BoardPreview className="relative" />
                        </div>
                    </section>

                    <section className="border-y bg-card/60">
                        <div className="mx-auto w-full max-w-6xl px-6 py-20">
                            <div className="max-w-2xl">
                                <p className="text-sm font-semibold text-primary">
                                    Everything the team needs
                                </p>
                                <h2 className="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">
                                    One workspace from idea to accepted result
                                </h2>
                                <p className="mt-4 text-muted-foreground">
                                    No plug-ins, no integrations to wire up. The
                                    pieces a student or startup team actually
                                    uses, connected end to end.
                                </p>
                            </div>
                            <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <article
                                        key={feature.title}
                                        className="group rounded-2xl border bg-card p-6 shadow-xs transition-shadow hover:shadow-md"
                                    >
                                        <span className="inline-flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                            <feature.icon className="size-5" />
                                        </span>
                                        <h3 className="mt-4 font-display text-base font-semibold">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="how-it-works"
                        className="mx-auto w-full max-w-6xl scroll-mt-16 px-6 py-20"
                    >
                        <div className="grid gap-12 lg:grid-cols-[1fr_1.4fr] lg:items-start">
                            <div>
                                <p className="text-sm font-semibold text-primary">
                                    How it works
                                </p>
                                <h2 className="mt-2 font-display text-3xl font-bold tracking-tight sm:text-4xl">
                                    A loop the whole team can follow
                                </h2>
                                <p className="mt-4 text-muted-foreground">
                                    Each sprint runs through the same four
                                    steps. Nothing is hidden behind a dashboard
                                    formula: the board shows the real count of
                                    what is left.
                                </p>
                            </div>
                            <ol className="relative grid gap-4 sm:grid-cols-2">
                                {steps.map((step) => (
                                    <li
                                        key={step.number}
                                        className="rounded-2xl border bg-card p-5 shadow-xs"
                                    >
                                        <span className="font-display text-sm font-bold text-brand-accent-foreground">
                                            <span className="mr-2 inline-block rounded-md bg-brand-accent/25 px-1.5 py-0.5 tabular-nums">
                                                {step.number}
                                            </span>
                                        </span>
                                        <h3 className="mt-3 font-display text-base font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="mt-1.5 text-sm text-muted-foreground">
                                            {step.description}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section className="mx-auto w-full max-w-6xl px-6 pb-20">
                        <div className="relative overflow-hidden rounded-3xl bg-primary px-8 py-14 text-primary-foreground sm:px-14">
                            <AppLogoIcon
                                aria-hidden="true"
                                className="pointer-events-none absolute -right-10 -bottom-16 size-72 opacity-10"
                            />
                            <div className="relative max-w-xl">
                                <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                                    Ready for the next sprint?
                                </h2>
                                <p className="mt-3 text-primary-foreground/80">
                                    Sign in with the account your administrator
                                    created for you and pick up where the team
                                    left off.
                                </p>
                                <div className="mt-8 flex flex-wrap items-center gap-3">
                                    <Button
                                        size="lg"
                                        variant="secondary"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                auth.user
                                                    ? dashboard()
                                                    : login()
                                            }
                                        >
                                            {auth.user ? (
                                                <>Open dashboard</>
                                            ) : (
                                                <>
                                                    <KeyRound />
                                                    Log in
                                                </>
                                            )}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-6 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <AppLogo size="sm" className="text-foreground" />
                        <p>
                            Agile project tracking for teams that want to see
                            their work clearly.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
