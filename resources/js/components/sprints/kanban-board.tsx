import KanbanColumn from '@/components/sprints/kanban-column';
import type { KanbanCard } from '@/pages/sprints/show';

type Props = {
    projectId: number;
    sprintId: number;
    board: Record<string, KanbanCard[]>;
    statuses: { value: string; label: string }[];
    onRemove?: (card: KanbanCard) => void;
};

/**
 * The status columns of a sprint.
 *
 * Cards carry an accessible status control, so the board needs no drag
 * and drop library.
 */
export default function KanbanBoard({
    projectId,
    sprintId,
    board,
    statuses,
    onRemove,
}: Props) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            {statuses.map((status) => (
                <KanbanColumn
                    key={status.value}
                    title={status.label}
                    status={status.value}
                    cards={board[status.value] ?? []}
                    projectId={projectId}
                    sprintId={sprintId}
                    statuses={statuses}
                    onRemove={onRemove}
                />
            ))}
        </div>
    );
}
