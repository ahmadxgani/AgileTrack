import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { show as projectShow } from '@/routes/projects';
import { index as reviewsIndex } from '@/routes/projects/reviews';

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

function formatDate(value: string | null): string {
    if (!value) {
        return 'None scheduled';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function Dashboard({ projects, summary }: Props) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Heading
                    title="Dashboard"
                    description={`${summary.projects} ${summary.projects === 1 ? 'project' : 'projects'} you can see`}
                />

                {projects.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No projects yet</CardTitle>
                            <CardDescription>
                                You are not a member of any project yet. Ask a
                                project manager to add you.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Project</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Active sprint</TableHead>
                                    <TableHead className="text-right">
                                        To do
                                    </TableHead>
                                    <TableHead className="text-right">
                                        In progress
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Done
                                    </TableHead>
                                    <TableHead>Next review</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {projects.map((project) => (
                                    <TableRow key={project.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={projectShow(project.id)}
                                                className="hover:underline"
                                            >
                                                {project.name}
                                            </Link>
                                            {project.archived_at && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-2"
                                                >
                                                    Archived
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {project.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {project.active_sprint ??
                                                'No active sprint'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {project.counts.todo}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {project.counts.in_progress}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {project.counts.done}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(project.next_review_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={reviewsIndex(
                                                        project.id,
                                                    )}
                                                >
                                                    Reviews
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
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
