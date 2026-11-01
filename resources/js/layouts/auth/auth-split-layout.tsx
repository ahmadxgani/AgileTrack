import { Link, usePage } from '@inertiajs/react';
import { CalendarRange, ClipboardCheck, ListChecks } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    {
        icon: ListChecks,
        title: 'One backlog',
        description: 'Ranked by priority, with an owner on every item.',
    },
    {
        icon: CalendarRange,
        title: 'A live sprint board',
        description: 'Every move is recorded with who made it and when.',
    },
    {
        icon: ClipboardCheck,
        title: 'Reviews with a decision',
        description: 'Accepted or sent back, never left hanging.',
    },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-svh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside className="relative hidden overflow-hidden bg-primary p-10 text-primary-foreground lg:flex lg:flex-col">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(40rem_30rem_at_0%_100%,var(--color-brand-accent)/35%,transparent)]"
                />
                <AppLogoIcon
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-24 -bottom-24 size-[28rem] opacity-10"
                />

                <Link href={home()} className="relative w-fit rounded-lg">
                    <AppLogo
                        size="md"
                        className="text-primary-foreground [&>span:first-child]:bg-primary-foreground [&>span:first-child]:text-primary"
                    />
                </Link>

                <div className="relative my-auto max-w-md py-12">
                    <h2 className="font-display text-3xl font-bold tracking-tight text-balance xl:text-4xl">
                        Everything the team needs to run the next sprint.
                    </h2>
                    <ul className="mt-10 space-y-6">
                        {highlights.map((item) => (
                            <li key={item.title} className="flex gap-4">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-foreground/15">
                                    <item.icon className="size-5" />
                                </span>
                                <div>
                                    <p className="font-display font-semibold">
                                        {item.title}
                                    </p>
                                    <p className="text-sm text-primary-foreground/75">
                                        {item.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-xs text-primary-foreground/60">
                    {name}. Agile project tracking for small teams.
                </p>
            </aside>

            <main className="flex flex-col px-6 py-8 sm:px-10">
                <Link
                    href={home()}
                    className="w-fit rounded-lg lg:hidden"
                    aria-label={name}
                >
                    <AppLogo size="sm" />
                </Link>

                <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
                    <div className="mb-8 space-y-2">
                        <h1 className="font-display text-2xl font-bold tracking-tight">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
