import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    archive as archiveRoute,
    create as createRoute,
    edit as editRoute,
    index as backlogIndex,
    status as statusRoute,
} from '@/routes/projects/backlog';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';

export type BacklogRow = {
    id: number;
    title: string;
    description: string | null;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    archived_at: string | null;
    assignees: { id: number; name: string }[];
    can: { update: boolean; update_status: boolean; archive: boolean };
};

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    items: BacklogRow[];
    statuses: { value: string; label: string }[];
    can: { create: boolean };
};

export default function BacklogIndex({ project, items, statuses, can }: Props) {
    const [confirmingArchive, setConfirmingArchive] = useState<number | null>(
        null,
    );

    const hasActions = items.some(
        (item) => item.can.update || item.can.archive,
    );

    function changeStatus(item: BacklogRow, status: string) {
        router.patch(
            statusRoute([project.id, item.id]).url,
            { status },
            { preserveScroll: true },
        );
    }

    function archive(item: BacklogRow) {
        router.patch(
            archiveRoute([project.id, item.id]).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setConfirmingArchive(null),
            },
        );
    }

    return (
        <>
            <Head title={`${project.name} backlog`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title="Backlog"
                            description={`Work planned for ${project.name}`}
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
                                    New item
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

                {items.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No backlog items yet</CardTitle>
                            <CardDescription>
                                {can.create
                                    ? 'Add the first item so the team has work to pick up.'
                                    : 'Nothing has been planned for this project yet.'}
                            </CardDescription>
                        </CardHeader>
                        {can.create && (
                            <CardContent>
                                <Button asChild>
                                    <Link href={createRoute(project.id)}>
                                        New item
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
                                    <TableHead>Title</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Assignees</TableHead>
                                    {hasActions && (
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            {item.title}
                                            {item.archived_at && (
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
                                                {item.priority_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {item.can.update_status ? (
                                                <Select
                                                    value={item.status}
                                                    onValueChange={(value) =>
                                                        changeStatus(
                                                            item,
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        size="sm"
                                                        aria-label={`Status for ${item.title}`}
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {statuses.map(
                                                            (status) => (
                                                                <SelectItem
                                                                    key={
                                                                        status.value
                                                                    }
                                                                    value={
                                                                        status.value
                                                                    }
                                                                >
                                                                    {
                                                                        status.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Badge variant="secondary">
                                                    {item.status_label}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {item.assignees.length === 0
                                                ? 'Unassigned'
                                                : item.assignees
                                                      .map(
                                                          (assignee) =>
                                                              assignee.name,
                                                      )
                                                      .join(', ')}
                                        </TableCell>
                                        {hasActions && (
                                            <TableCell className="text-right">
                                                {confirmingArchive ===
                                                item.id ? (
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label={`Confirm archiving ${item.title}`}
                                                            onClick={() =>
                                                                archive(item)
                                                            }
                                                        >
                                                            Confirm
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setConfirmingArchive(
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <div className="flex justify-end gap-2">
                                                        {item.can.update && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={editRoute(
                                                                        [
                                                                            project.id,
                                                                            item.id,
                                                                        ],
                                                                    )}
                                                                >
                                                                    Edit
                                                                </Link>
                                                            </Button>
                                                        )}
                                                        {item.can.archive && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setConfirmingArchive(
                                                                        item.id,
                                                                    )
                                                                }
                                                            >
                                                                Archive
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                        )}
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

BacklogIndex.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Backlog', href: backlogIndex(props.project.id) },
    ],
});
