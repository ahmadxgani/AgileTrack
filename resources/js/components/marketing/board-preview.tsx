import { cn } from '@/lib/utils';

type Card = {
    title: string;
    priority: 'Low' | 'Medium' | 'High' | 'Urgent';
    assignees: string[];
};

type Column = {
    name: string;
    tone: string;
    cards: Card[];
};

const columns: Column[] = [
    {
        name: 'To do',
        tone: 'bg-muted-foreground/50',
        cards: [
            {
                title: 'Write acceptance criteria for onboarding',
                priority: 'Medium',
                assignees: ['RA'],
            },
            {
                title: 'Design the review summary email',
                priority: 'Low',
                assignees: ['DP', 'NA'],
            },
        ],
    },
    {
        name: 'In progress',
        tone: 'bg-brand-accent',
        cards: [
            {
                title: 'Sprint board drag and drop',
                priority: 'High',
                assignees: ['AG'],
            },
            {
                title: 'Member roles and permissions',
                priority: 'Urgent',
                assignees: ['AG', 'RA'],
            },
        ],
    },
    {
        name: 'Done',
        tone: 'bg-primary',
        cards: [
            {
                title: 'Project backlog with priorities',
                priority: 'High',
                assignees: ['NA'],
            },
            {
                title: 'Passkey and two-factor sign-in',
                priority: 'Medium',
                assignees: ['DP'],
            },
        ],
    },
];

const priorityTone: Record<Card['priority'], string> = {
    Low: 'bg-muted text-muted-foreground',
    Medium: 'bg-secondary text-secondary-foreground',
    High: 'bg-accent text-accent-foreground',
    Urgent: 'bg-brand-accent/25 text-brand-accent-foreground',
};

/**
 * A static, illustrative sprint board for the landing page. It uses the
 * same tokens as the real Kanban so the preview always matches the theme.
 */
export default function BoardPreview({ className }: { className?: string }) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-2xl shadow-primary/10',
                className,
            )}
        >
            <div className="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        Sprint 4
                    </p>
                    <p className="text-sm font-semibold">
                        Ship the review workflow
                    </p>
                </div>
                <div className="flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                    <span className="size-1.5 rounded-full bg-primary" />
                    Active
                </div>
            </div>

            <div className="grid grid-cols-3 gap-3 p-3 sm:p-4">
                {columns.map((column) => (
                    <div key={column.name} className="min-w-0 space-y-2">
                        <div className="flex items-center gap-2 px-1 text-xs font-medium text-muted-foreground">
                            <span
                                className={cn(
                                    'size-2 rounded-full',
                                    column.tone,
                                )}
                            />
                            <span className="truncate">{column.name}</span>
                            <span className="ml-auto tabular-nums">
                                {column.cards.length}
                            </span>
                        </div>

                        {column.cards.map((card) => (
                            <div
                                key={card.title}
                                className="space-y-2 rounded-lg border bg-background p-2.5 shadow-xs"
                            >
                                <p className="line-clamp-2 text-xs leading-snug font-medium">
                                    {card.title}
                                </p>
                                <div className="flex items-center justify-between gap-2">
                                    <span
                                        className={cn(
                                            'rounded-md px-1.5 py-0.5 text-[10px] font-semibold',
                                            priorityTone[card.priority],
                                        )}
                                    >
                                        {card.priority}
                                    </span>
                                    <div className="flex -space-x-1.5">
                                        {card.assignees.map((initials) => (
                                            <span
                                                key={initials}
                                                className="flex size-5 items-center justify-center rounded-full border-2 border-background bg-primary text-[9px] font-semibold text-primary-foreground"
                                            >
                                                {initials}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
