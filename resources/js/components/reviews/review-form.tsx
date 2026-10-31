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
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/projects/reviews';

export type ReviewFormValues = {
    sprint_id: string;
    scheduled_at: string;
    notes: string;
    feedback: string;
};

type Props = {
    projectId: number;
    sprints: { id: number; name: string }[];
    review?: ReviewFormValues & { id: number };
};

const GENERAL = 'general';

export default function ReviewForm({ projectId, sprints, review }: Props) {
    const form = useForm<ReviewFormValues>({
        sprint_id: review?.sprint_id ?? GENERAL,
        scheduled_at: review?.scheduled_at ?? '',
        notes: review?.notes ?? '',
        feedback: review?.feedback ?? '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        // The general option means "project wide review", which the server
        // stores as a null sprint.
        form.transform((data) => ({
            ...data,
            sprint_id: data.sprint_id === GENERAL ? null : data.sprint_id,
        }));

        if (review) {
            form.patch(update([projectId, review.id]).url);
        } else {
            form.post(store(projectId).url);
        }
    }

    return (
        <form onSubmit={submit} className="max-w-xl space-y-6">
            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="sprint_id">Sprint</Label>
                    <Select
                        value={form.data.sprint_id}
                        onValueChange={(value) =>
                            form.setData('sprint_id', value)
                        }
                    >
                        <SelectTrigger
                            id="sprint_id"
                            className="w-full"
                            aria-invalid={Boolean(form.errors.sprint_id)}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={GENERAL}>
                                Whole project
                            </SelectItem>
                            {sprints.map((sprint) => (
                                <SelectItem
                                    key={sprint.id}
                                    value={String(sprint.id)}
                                >
                                    {sprint.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.sprint_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="scheduled_at">Scheduled for</Label>
                    <Input
                        id="scheduled_at"
                        type="datetime-local"
                        value={form.data.scheduled_at}
                        onChange={(event) =>
                            form.setData('scheduled_at', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.scheduled_at)}
                    />
                    <InputError message={form.errors.scheduled_at} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="notes">Agenda and notes</Label>
                <Textarea
                    id="notes"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    rows={4}
                    aria-invalid={Boolean(form.errors.notes)}
                />
                <InputError message={form.errors.notes} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="feedback">Feedback</Label>
                <Textarea
                    id="feedback"
                    value={form.data.feedback}
                    onChange={(event) =>
                        form.setData('feedback', event.target.value)
                    }
                    rows={4}
                    aria-invalid={Boolean(form.errors.feedback)}
                />
                <InputError message={form.errors.feedback} />
            </div>

            <Button disabled={form.processing}>
                {review ? 'Save review' : 'Schedule review'}
            </Button>
        </form>
    );
}
