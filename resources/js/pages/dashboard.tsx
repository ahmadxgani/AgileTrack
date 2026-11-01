import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CircleDashed,
    CircleDot,
    FolderKanban,
} from 'lucide-react';
import ProjectCard from '@/components/dashboard/project-card';
import StatCard from '@/components/dashboard/stat-card';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as projectsIndex } from '@/routes/projects';

export type DashboardProject = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    archived_at: string | null;
    active_sprint: string | null;
    next_review_at: string | null;
    counts: { todo: number; in_progress: number; done: number };
};

type Props = {
    projects: DashboardProject[];
    summary: { projects: number };
};

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

export default function Dashboard({ projects, summary }: Props) {
    const { auth } = usePage().props;

    const totals = projects.reduce(
        (sum, project) => ({
            todo: sum.todo + project.counts.todo,
            in_progress: sum.in_progress + project.counts.in_progress,
            done: sum.done + project.counts.done,
        }),
        { todo: 0, in_progress: 0, done: 0 },
    );
    const activeSprints = projects.filter((p) => p.active_sprint).length;
    const upcomingReviews = projects.filter((p) => p.next_review_at).length;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto w-full max-w-7xl space-y-8 p-4 sm:p-6 lg:p-8">
                <Heading
                    title={`${greeting()}, ${firstName}`}
                    description={
                        summary.projects === 0
                            ? 'You are not a member of any project yet.'
                            : `Here is where your ${summary.projects === 1 ? 'project stands' : `${summary.projects} projects stand`} today.`
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Projects"
                        value={summary.projects}
                        hint={`${activeSprints} with an active sprint`}
                        icon={FolderKanban}
                        tone="primary"
                    />
                    <StatCard
                        label="To do"
                        value={totals.todo}
                        hint="Open items across your projects"
                        icon={CircleDashed}
                    />
                    <StatCard
                        label="In progress"
                        value={totals.in_progress}
                        hint="Being worked on right now"
                        icon={CircleDot}
                        tone="accent"
                    />
                    <StatCard
                        label="Upcoming reviews"
                        value={upcomingReviews}
                        hint="Projects with a review scheduled"
                        icon={CalendarClock}
                    />
                </div>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <h3 className="font-display text-lg font-semibold">
                            Your projects
                        </h3>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={projectsIndex()}>View all</Link>
                        </Button>
                    </div>

                    {projects.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed bg-card/50 px-6 py-16 text-center">
                            <span className="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <FolderKanban className="size-6" />
                            </span>
                            <p className="font-display text-base font-semibold">
                                No projects yet
                            </p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Ask a project manager to add you to a project
                                and it will show up here with its sprint and
                                review status.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {projects.map((project) => (
                                <ProjectCard
                                    key={project.id}
                                    project={project}
                                />
                            ))}
                        </div>
                    )}
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
