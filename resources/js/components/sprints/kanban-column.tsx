import type { KanbanCard } from '@/pages/sprints/show';
import WorkItemCard from '@/components/sprints/work-item-card';

type Props = {
    title: string;
    status: string;
    cards: KanbanCard[];
    projectId: number;
    sprintId: number;
    statuses: { value: string; label: string }[];
    onRemove?: (card: KanbanCard) => void;
};

export default function KanbanColumn({
    title,
    status,
    cards,
    projectId,
    sprintId,
    statuses,
    onRemove,
}: Props) {
    return (
        <section
            aria-labelledby={`column-${status}`}
            className="flex min-h-24 flex-col gap-2 rounded-xl border bg-muted/40 p-3"
        >
            <h2
                id={`column-${status}`}
                className="flex items-center justify-between text-sm font-medium"
            >
                {title}
                <span className="text-muted-foreground">{cards.length}</span>
            </h2>

            {cards.length === 0 ? (
                <p className="text-sm text-muted-foreground">Nothing here.</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {cards.map((card) => (
                        <WorkItemCard
                            key={card.id}
                            card={card}
                            projectId={projectId}
                            sprintId={sprintId}
                            statuses={statuses}
                            onRemove={onRemove}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}
