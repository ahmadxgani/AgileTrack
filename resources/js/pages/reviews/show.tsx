import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';
import {
    decision as decisionRoute,
    edit as editRoute,
    index,
    show,
    update,
} from '@/routes/projects/reviews';

export type ReviewDetail = {
    id: number;
    sprint: string | null;
    scheduled_at: string | null;
    notes: string | null;
    feedback: string | null;
    decision: string;
    decision_label: string;
    decided_by: string | null;
    decided_at: string | null;
    created_by: string | null;
    follow_up: { id: number; title: string } | null;
};

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    review: ReviewDetail;
    decisions: { value: string; label: string }[];
    can: { update: boolean; record_feedback: boolean; decide: boolean };
};

export default function ReviewsShow({
    project,
    review,
    decisions,
    can,
}: Props) {
    const { errors } = usePage().props;
    const [decision, setDecision] = useState(review.decision);
    const [followUpTitle, setFollowUpTitle] = useState('');

    const feedbackForm = useForm({ feedback: review.feedback ?? '' });

    function submitFeedback(event: React.FormEvent) {
        event.preventDefault();
        feedbackForm.patch(update([project.id, review.id]).url, {
            preserveScroll: true,
        });
    }

    function submitDecision(event: React.FormEvent) {
        event.preventDefault();
        router.patch(
            decisionRoute([project.id, review.id]).url,
            // Only needs_changes without a linked item needs a title; the
            // server ignores it otherwise.
            decision === 'needs_changes' && !review.follow_up
                ? { decision, follow_up_title: followUpTitle }
                : { decision },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Review - ${project.name}`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title="Review"
                            description={review.sprint ?? 'Whole project'}
                        />

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant={
                                    review.decision === 'approved'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {review.decision_label}
                            </Badge>
                            {project.archived_at && (
                                <Badge variant="outline">
                                    Archived project, read only
                                </Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={editRoute([project.id, review.id])}>
                                    Edit
                                </Link>
                            </Button>
                        )}

                        <Button variant="ghost" asChild>
                            <Link href={index(project.id)}>All reviews</Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <div>
                            <span className="text-muted-foreground">
                                Scheduled for:{' '}
                            </span>
                            {review.scheduled_at ?? 'Not scheduled'}
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Created by:{' '}
                            </span>
                            {review.created_by ?? 'Unknown'}
                        </div>
                        {review.decided_by && (
                            <div>
                                <span className="text-muted-foreground">
                                    Decided by:{' '}
                                </span>
                                {review.decided_by}
                                {review.decided_at &&
                                    ` on ${review.decided_at}`}
                            </div>
                        )}
                        <div className="whitespace-pre-wrap">
                            <span className="text-muted-foreground">
                                Agenda and notes:{' '}
                            </span>
                            {review.notes || 'None'}
                        </div>
                        {review.follow_up && (
                            <div>
                                <span className="text-muted-foreground">
                                    Follow-up work:{' '}
                                </span>
                                {review.follow_up.title}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Feedback</CardTitle>
                        <CardDescription>
                            {can.record_feedback
                                ? 'Share what the team saw in the review.'
                                : 'Feedback captured during the review.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {can.record_feedback ? (
                            <form
                                onSubmit={submitFeedback}
                                className="space-y-3"
                            >
                                <Label htmlFor="feedback" className="sr-only">
                                    Feedback
                                </Label>
                                <Textarea
                                    id="feedback"
                                    value={feedbackForm.data.feedback}
                                    onChange={(event) =>
                                        feedbackForm.setData(
                                            'feedback',
                                            event.target.value,
                                        )
                                    }
                                    rows={4}
                                    aria-invalid={Boolean(
                                        feedbackForm.errors.feedback,
                                    )}
                                />
                                <InputError
                                    message={feedbackForm.errors.feedback}
                                />
                                <Button disabled={feedbackForm.processing}>
                                    Save feedback
                                </Button>
                            </form>
                        ) : (
                            <p className="text-sm whitespace-pre-wrap">
                                {review.feedback || 'No feedback yet.'}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {can.decide && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Decision</CardTitle>
                            <CardDescription>
                                Approve the sprint or send it back for changes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitDecision}
                                className="space-y-4"
                            >
                                <div
                                    className="flex flex-col gap-2"
                                    role="radiogroup"
                                    aria-label="Decision"
                                >
                                    {decisions.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="radio"
                                                name="decision"
                                                value={option.value}
                                                checked={
                                                    decision === option.value
                                                }
                                                onChange={() =>
                                                    setDecision(option.value)
                                                }
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>

                                {decision === 'needs_changes' &&
                                    !review.follow_up && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="follow_up_title">
                                                Follow-up work
                                            </Label>
                                            <Input
                                                id="follow_up_title"
                                                value={followUpTitle}
                                                onChange={(event) =>
                                                    setFollowUpTitle(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="What needs to change"
                                                aria-invalid={Boolean(
                                                    errors.follow_up_title,
                                                )}
                                            />
                                            <InputError
                                                message={errors.follow_up_title}
                                            />
                                        </div>
                                    )}

                                <InputError message={errors.decision} />

                                <Button>Record decision</Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

ReviewsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Reviews', href: index(props.project.id) },
        {
            title: 'Review',
            href: show([props.project.id, props.review.id]),
        },
    ],
});
