import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/sprints';

export type SprintFormValues = {
    name: string;
    goal: string;
    starts_at: string;
    ends_at: string;
};

type Props = {
    projectId: number;
    sprint?: SprintFormValues & { id: number };
};

export default function SprintForm({ projectId, sprint }: Props) {
    const form = useForm<SprintFormValues>({
        name: sprint?.name ?? '',
        goal: sprint?.goal ?? '',
        starts_at: sprint?.starts_at ?? '',
        ends_at: sprint?.ends_at ?? '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (sprint) {
            form.patch(update([projectId, sprint.id]).url);

            return;
        }

        form.post(store(projectId).url);
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
                <Label htmlFor="goal">Goal</Label>
                <Textarea
                    id="goal"
                    value={form.data.goal}
                    onChange={(event) =>
                        form.setData('goal', event.target.value)
                    }
                    rows={3}
                    aria-invalid={Boolean(form.errors.goal)}
                    aria-describedby={
                        form.errors.goal ? 'goal-error' : undefined
                    }
                />
                <InputError id="goal-error" message={form.errors.goal} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="starts_at">Starts</Label>
                    <Input
                        id="starts_at"
                        type="date"
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                        required
                        aria-invalid={Boolean(form.errors.starts_at)}
                        aria-describedby={
                            form.errors.starts_at ? 'starts-error' : undefined
                        }
                    />
                    <InputError
                        id="starts-error"
                        message={form.errors.starts_at}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ends_at">Ends</Label>
                    <Input
                        id="ends_at"
                        type="date"
                        value={form.data.ends_at}
                        onChange={(event) =>
                            form.setData('ends_at', event.target.value)
                        }
                        required
                        aria-invalid={Boolean(form.errors.ends_at)}
                        aria-describedby={
                            form.errors.ends_at ? 'ends-error' : undefined
                        }
                    />
                    <InputError id="ends-error" message={form.errors.ends_at} />
                </div>
            </div>

            <Button disabled={form.processing}>
                {sprint ? 'Save sprint' : 'Create sprint'}
            </Button>
        </form>
    );
}
