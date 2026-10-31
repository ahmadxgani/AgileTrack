import { Head, Link, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import KanbanBoard from '@/components/sprints/kanban-board';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { close, edit as editRoute, index, show, start } from '@/routes/sprints';
import { destroy, store } from '@/routes/sprints/items';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';

export type KanbanCard = {
    id: number;
    title: string;
    priority_label: string;
    assignees: { id: number; name: string }[];
    can_move_status: boolean;
};

export type SprintDetail = {
    id: number;
    name: string;
    goal: string | null;
    starts_at: string;
    ends_at: string;
    status: string;
    status_label: string;
};

export type SprintItem = { id: number; title: string; priority_label: string };

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    sprint: SprintDetail;
    board: Record<string, KanbanCard[]>;
    pool: SprintItem[];
    statuses: { value: string; label: string }[];
    can: {
        update: boolean;
        start: boolean;
        close: boolean;
        manage_items: boolean;
    };
};

export default function SprintsShow({
    project,
    sprint,
    board,
    pool,
    statuses,
    can,
}: Props) {
    const { errors } = usePage().props;

    function addItem(item: SprintItem) {
        router.post(
            store([project.id, sprint.id]).url,
            { backlog_item_id: item.id },
            { preserveScroll: true },
        );
    }

    function removeItem(card: KanbanCard) {
        router.delete(destroy([project.id, sprint.id]).url, {
            data: { backlog_item_id: card.id },
            preserveScroll: true,
        });
    }

    function startSprint() {
        router.patch(
            start([project.id, sprint.id]).url,
            {},
            { preserveScroll: true },
        );
    }

    function closeSprint() {
        router.patch(
            close([project.id, sprint.id]).url,
            {},
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={sprint.name} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading title={sprint.name} />

                        <p className="text-sm text-muted-foreground">
                            {sprint.starts_at} to {sprint.ends_at}
                        </p>

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                {sprint.status_label}
                            </Badge>
                            {project.archived_at && (
                                <Badge variant="outline">
                                    Archived project, read only
                                </Badge>
                            )}
                        </div>

                        {sprint.goal && (
                            <p className="max-w-prose text-sm">{sprint.goal}</p>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={editRoute([project.id, sprint.id])}>
                                    Edit
                                </Link>
                            </Button>
                        )}

                        {can.start && (
                            <Button variant="outline" onClick={startSprint}>
                                Start sprint
                            </Button>
                        )}

                        {can.close && (
                            <Button variant="outline" onClick={closeSprint}>
                                Close sprint
                            </Button>
                        )}

                        <Button variant="ghost" asChild>
                            <Link href={index(project.id)}>All sprints</Link>
                        </Button>
                    </div>
                </div>

                {/* Sprint-level rejections: starting or closing the sprint,
                    and adding or removing work. A rejected status move is
                    shown on its own card instead. */}
                <InputError message={errors.sprint} />
                <InputError message={errors.backlog_item_id} />

                <KanbanBoard
                    projectId={project.id}
                    sprintId={sprint.id}
                    board={board}
                    statuses={statuses}
                    onRemove={can.manage_items ? removeItem : undefined}
                />

                {can.manage_items && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Backlog pool</CardTitle>
                            <CardDescription>
                                Unplanned work of this project. Add it to the
                                sprint to pull it onto the board.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {pool.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No unplanned items are left to add.
                                </p>
                            )}

                            <ul className="divide-y">
                                {pool.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span>{item.title}</span>
                                            <Badge variant="secondary">
                                                {item.priority_label}
                                            </Badge>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => addItem(item)}
                                        >
                                            Add to sprint
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

SprintsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Sprints', href: index(props.project.id) },
        {
            title: props.sprint.name,
            href: show([props.project.id, props.sprint.id]),
        },
    ],
});
