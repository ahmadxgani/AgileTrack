import { Link } from '@inertiajs/react';
import { ArrowUpRight, CalendarClock, CalendarRange } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { DashboardProject } from '@/pages/dashboard';
import { show as projectShow } from '@/routes/projects';
import { index as reviewsIndex } from '@/routes/projects/reviews';

function formatDate(value: string | null): string {
    if (!value) {
        return 'None scheduled';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

const segments = [
    { key: 'done', label: 'Done', className: 'bg-primary' },
    { key: 'in_progress', label: 'In progress', className: 'bg-brand-accent' },
    { key: 'todo', label: 'To do', className: 'bg-muted-foreground/30' },
] as const;

export default function ProjectCard({
    project,
}: {
    project: DashboardProject;
}) {
    const total =
        project.counts.todo + project.counts.in_progress + project.counts.done;

    return (
        <article className="group flex flex-col gap-5 rounded-2xl border bg-card p-5 shadow-xs transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Link
                        href={projectShow(project.id)}
                        className="font-display text-lg font-semibold tracking-tight hover:text-primary"
                    >
                        {project.name}
                    </Link>
                    <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <Badge variant="secondary">
                            {project.status_label}
                        </Badge>
                        {project.archived_at && (
                            <Badge variant="outline">Archived</Badge>
                        )}
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    asChild
                    className="shrink-0 text-muted-foreground"
                >
                    <Link
                        href={projectShow(project.id)}
                        aria-label={`Open ${project.name}`}
                    >
                        <ArrowUpRight />
                    </Link>
                </Button>
            </div>

            <div>
                <div className="flex items-baseline justify-between text-sm">
                    <span className="font-medium">Work</span>
                    <span className="text-muted-foreground tabular-nums">
                        {project.counts.done} of {total} done
                    </span>
                </div>
                <div
                    className="mt-2 flex h-2 overflow-hidden rounded-full bg-muted"
                    role="img"
                    aria-label={`${project.counts.done} done, ${project.counts.in_progress} in progress, ${project.counts.todo} to do`}
                >
                    {total > 0 &&
                        segments.map((segment) => (
                            <span
                                key={segment.key}
                                className={segment.className}
                                style={{
                                    width: `${(project.counts[segment.key] / total) * 100}%`,
                                }}
                            />
                        ))}
                </div>
                <dl className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    {segments.map((segment) => (
                        <div
                            key={segment.key}
                            className="flex items-center gap-1.5"
                        >
                            <span
                                className={`size-2 rounded-full ${segment.className}`}
                            />
                            <dt className="text-muted-foreground">
                                {segment.label}
                            </dt>
                            <dd className="font-medium tabular-nums">
                                {project.counts[segment.key]}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>

            <dl className="grid gap-2 border-t pt-4 text-sm">
                <div className="flex items-center gap-2">
                    <CalendarRange className="size-4 shrink-0 text-muted-foreground" />
                    <dt className="text-muted-foreground">Sprint</dt>
                    <dd className="ml-auto truncate font-medium">
                        {project.active_sprint ?? 'No active sprint'}
                    </dd>
                </div>
                <div className="flex items-center gap-2">
                    <CalendarClock className="size-4 shrink-0 text-muted-foreground" />
                    <dt className="text-muted-foreground">Next review</dt>
                    <dd className="ml-auto truncate font-medium">
                        <Link
                            href={reviewsIndex(project.id)}
                            className="hover:text-primary"
                        >
                            {formatDate(project.next_review_at)}
                        </Link>
                    </dd>
                </div>
            </dl>
        </article>
    );
}
