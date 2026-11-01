import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
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
import { create as createRoute, index, show } from '@/routes/sprints';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';

export type SprintRow = {
    id: number;
    name: string;
    goal: string | null;
    starts_at: string;
    ends_at: string;
    status: string;
    status_label: string;
    items: number;
};

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    sprints: SprintRow[];
    can: { create: boolean };
};

export default function SprintsIndex({ project, sprints, can }: Props) {
    return (
        <>
            <Head title={`${project.name} sprints`} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title="Sprints"
                            description={`Time boxes of ${project.name}`}
                        />

                        {project.archived_at && (
                            <Badge variant="outline">
                                Archived project, read only
                            </Badge>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {can.create && (
                            <Button asChild>
                                <Link href={createRoute(project.id)}>
                                    New sprint
                                </Link>
                            </Button>
                        )}

                        <Button variant="ghost" asChild>
                            <Link href={projectShow(project.id)}>
                                Back to project
                            </Link>
                        </Button>
                    </div>
                </div>

                {sprints.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No sprints yet</CardTitle>
                            <CardDescription>
                                {can.create
                                    ? 'Plan the first sprint so the team shares one time box.'
                                    : 'This project has no sprints yet.'}
                            </CardDescription>
                        </CardHeader>
                        {can.create && (
                            <CardContent>
                                <Button asChild>
                                    <Link href={createRoute(project.id)}>
                                        New sprint
                                    </Link>
                                </Button>
                            </CardContent>
                        )}
                    </Card>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Dates</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Items</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sprints.map((sprint) => (
                                    <TableRow key={sprint.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={show([
                                                    project.id,
                                                    sprint.id,
                                                ])}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {sprint.name}
                                            </Link>
                                            {sprint.goal && (
                                                <p className="text-sm text-muted-foreground">
                                                    {sprint.goal}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {sprint.starts_at} to{' '}
                                            {sprint.ends_at}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {sprint.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{sprint.items}</TableCell>
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

SprintsIndex.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Sprints', href: index(props.project.id) },
    ],
});
