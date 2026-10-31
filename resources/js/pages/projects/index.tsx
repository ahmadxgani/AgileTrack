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
import { create, index, show } from '@/routes/projects';

export type ProjectRow = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    starts_at: string | null;
    ends_at: string | null;
    members: number;
    archived_at: string | null;
};

type Props = {
    projects: ProjectRow[];
    can: { create: boolean };
};

export default function ProjectsIndex({ projects, can }: Props) {
    return (
        <>
            <Head title="Projects" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Projects"
                        description="Every project you can work on"
                    />

                    {can.create && (
                        <Button asChild>
                            <Link href={create()}>New project</Link>
                        </Button>
                    )}
                </div>

                {projects.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No projects yet</CardTitle>
                            <CardDescription>
                                {can.create
                                    ? 'Create the first project to start planning work.'
                                    : 'You are not a member of any project yet. Ask a project manager to add you.'}
                            </CardDescription>
                        </CardHeader>
                        {can.create && (
                            <CardContent>
                                <Button asChild>
                                    <Link href={create()}>New project</Link>
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
                                    <TableHead>Status</TableHead>
                                    <TableHead>Starts</TableHead>
                                    <TableHead>Ends</TableHead>
                                    <TableHead>Members</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {projects.map((project) => (
                                    <TableRow key={project.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={show(project.id)}
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
                                            {project.starts_at ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {project.ends_at ?? '—'}
                                        </TableCell>
                                        <TableCell>{project.members}</TableCell>
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

ProjectsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
    ],
};
