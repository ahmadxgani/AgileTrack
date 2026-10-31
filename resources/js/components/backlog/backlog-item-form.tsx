import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/projects/backlog';

export type BacklogItemFormValues = {
    title: string;
    description: string;
    priority: string;
    status: string;
    assignees: number[];
};

type Props = {
    projectId: number;
    item?: BacklogItemFormValues & { id: number };
    members: { id: number; name: string }[];
    priorities: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
};

export default function BacklogItemForm({
    projectId,
    item,
    members,
    priorities,
    statuses,
}: Props) {
    const form = useForm<BacklogItemFormValues>({
        title: item?.title ?? '',
        description: item?.description ?? '',
        priority: item?.priority ?? 'medium',
        status: item?.status ?? 'todo',
        assignees: item?.assignees ?? [],
    });

    function toggleAssignee(id: number, checked: boolean) {
        form.setData(
            'assignees',
            checked
                ? [...form.data.assignees, id]
                : form.data.assignees.filter((value) => value !== id),
        );
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (item) {
            form.patch(update([projectId, item.id]).url);
        } else {
            form.post(store(projectId).url);
        }
    }

    return (
        <form onSubmit={submit} className="max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="title">Title</Label>
                <Input
                    id="title"
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    required
                    aria-invalid={Boolean(form.errors.title)}
                    aria-describedby={
                        form.errors.title ? 'title-error' : undefined
                    }
                />
                <InputError id="title-error" message={form.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                    id="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    rows={4}
                    aria-invalid={Boolean(form.errors.description)}
                    aria-describedby={
                        form.errors.description
                            ? 'description-error'
                            : undefined
                    }
                />
                <InputError
                    id="description-error"
                    message={form.errors.description}
                />
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="priority">Priority</Label>
                    <Select
                        value={form.data.priority}
                        onValueChange={(value) =>
                            form.setData('priority', value)
                        }
                    >
                        <SelectTrigger
                            id="priority"
                            className="w-full"
                            aria-invalid={Boolean(form.errors.priority)}
                            aria-describedby={
                                form.errors.priority
                                    ? 'priority-error'
                                    : undefined
                            }
                        >
                            <SelectValue placeholder="Select a priority" />
                        </SelectTrigger>
                        <SelectContent>
                            {priorities.map((priority) => (
                                <SelectItem
                                    key={priority.value}
                                    value={priority.value}
                                >
                                    {priority.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        id="priority-error"
                        message={form.errors.priority}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="status">Status</Label>
                    <Select
                        value={form.data.status}
                        onValueChange={(value) => form.setData('status', value)}
                    >
                        <SelectTrigger
                            id="status"
                            className="w-full"
                            aria-invalid={Boolean(form.errors.status)}
                            aria-describedby={
                                form.errors.status ? 'status-error' : undefined
                            }
                        >
                            <SelectValue placeholder="Select a status" />
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
                    <InputError
                        id="status-error"
                        message={form.errors.status}
                    />
                </div>
            </div>

            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Assignees</legend>
                <p className="text-sm text-muted-foreground">
                    Only project members can be assigned.
                </p>

                {members.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        The project has no members yet.
                    </p>
                )}

                {members.map((member) => (
                    <div key={member.id} className="flex items-center gap-2">
                        <Checkbox
                            id={`assignee-${member.id}`}
                            checked={form.data.assignees.includes(member.id)}
                            onCheckedChange={(checked) =>
                                toggleAssignee(member.id, checked === true)
                            }
                        />
                        <Label
                            htmlFor={`assignee-${member.id}`}
                            className="font-normal"
                        >
                            {member.name}
                        </Label>
                    </div>
                ))}

                <InputError message={form.errors.assignees} />
                {Object.entries(form.errors)
                    .filter(([key]) => key.startsWith('assignees.'))
                    .map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
            </fieldset>

            <Button disabled={form.processing}>
                {item ? 'Save item' : 'Create item'}
            </Button>
        </form>
    );
}
