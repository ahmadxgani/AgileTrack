import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { KanbanCard } from '@/pages/sprints/show';
import { status as statusRoute } from '@/routes/sprints/items';

type Props = {
    card: KanbanCard;
    projectId: number;
    sprintId: number;
    statuses: { value: string; label: string }[];
    onRemove?: (card: KanbanCard) => void;
};

/**
 * One piece of work on the board.
 *
 * The status control is a Select rather than drag and drop, so it works
 * with a keyboard and a screen reader. Its choice is local until the
 * request settles, then cleared, so a repeated choice still fires.
 */
export default function WorkItemCard({
    card,
    projectId,
    sprintId,
    statuses,
    onRemove,
}: Props) {
    const [pending, setPending] = useState('');

    const { errors } = usePage().props;
    // A rejected move is keyed by card, so the message lands on the control
    // that was used and not on some other card or the top of the page.
    const error = errors[`items.${card.id}.status`];
    const errorId = `status-error-${card.id}`;

    function move(status: string) {
        setPending(status);

        router.patch(
            statusRoute([projectId, sprintId, card.id]).url,
            { status },
            {
                preserveScroll: true,
                onFinish: () => setPending(''),
            },
        );
    }

    return (
        <li>
            <Card className="gap-3 py-3">
                <CardContent className="space-y-2 px-3">
                    <p className="font-medium">{card.title}</p>

                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{card.priority_label}</Badge>
                        {card.assignees.length > 0 && (
                            <span className="text-xs text-muted-foreground">
                                {card.assignees
                                    .map((assignee) => assignee.name)
                                    .join(', ')}
                            </span>
                        )}
                    </div>

                    {onRemove && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-auto p-0 text-xs text-muted-foreground"
                            onClick={() => onRemove(card)}
                        >
                            Remove from sprint
                        </Button>
                    )}

                    {card.can_move_status && (
                        <div className="space-y-1">
                            <Select
                                value={pending}
                                onValueChange={move}
                                disabled={pending !== ''}
                            >
                                <SelectTrigger
                                    size="sm"
                                    className="w-full"
                                    aria-label={`Move ${card.title}`}
                                    aria-invalid={Boolean(error)}
                                    aria-describedby={
                                        error ? errorId : undefined
                                    }
                                >
                                    <SelectValue placeholder="Move to..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((status) => (
                                        <SelectItem
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <InputError id={errorId} message={error} />
                        </div>
                    )}
                </CardContent>
            </Card>
        </li>
    );
}
