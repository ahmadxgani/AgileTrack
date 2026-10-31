import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store, update } from '@/routes/projects';

export type ProjectFormValues = {
    name: string;
    description: string;
    starts_at: string;
    ends_at: string;
    status: string;
};

type Props = {
    project?: ProjectFormValues & { id: number };
    statuses: { value: string; label: string }[];
};

export default function ProjectForm({ project, statuses }: Props) {
    const form = useForm<ProjectFormValues>({
        name: project?.name ?? '',
        description: project?.description ?? '',
        starts_at: project?.starts_at ?? '',
        ends_at: project?.ends_at ?? '',
        status: project?.status ?? statuses[0]?.value ?? 'planned',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (project) {
            form.patch(update(project.id).url);
        } else {
            form.post(store().url);
        }
    }

    return (
        <form onSubmit={submit} className="max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    required
                    aria-invalid={Boolean(form.errors.name)}
                    aria-describedby={
                        form.errors.name ? 'name-error' : undefined
                    }
                />
                <InputError id="name-error" message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <textarea
                    id="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    rows={4}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
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
                    <Label htmlFor="starts_at">Starts on</Label>
                    <Input
                        id="starts_at"
                        type="date"
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.starts_at)}
                        aria-describedby={
                            form.errors.starts_at
                                ? 'starts-at-error'
                                : undefined
                        }
                    />
                    <InputError
                        id="starts-at-error"
                        message={form.errors.starts_at}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ends_at">Ends on</Label>
                    <Input
                        id="ends_at"
                        type="date"
                        value={form.data.ends_at}
                        onChange={(event) =>
                            form.setData('ends_at', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.ends_at)}
                        aria-describedby={
                            form.errors.ends_at ? 'ends-at-error' : undefined
                        }
                    />
                    <InputError
                        id="ends-at-error"
                        message={form.errors.ends_at}
                    />
                </div>
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
                            <SelectItem key={status.value} value={status.value}>
                                {status.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError id="status-error" message={form.errors.status} />
            </div>

            <Button disabled={form.processing}>
                {project ? 'Save project' : 'Create project'}
            </Button>
        </form>
    );
}
