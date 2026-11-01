import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import ReviewForm from '@/components/reviews/review-form';
import { Button } from '@/components/ui/button';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';
import { index, show } from '@/routes/projects/reviews';

export type EditableReview = {
    id: number;
    sprint_id: string;
    scheduled_at: string;
    notes: string;
    feedback: string;
};

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    sprints: { id: number; name: string }[];
    review: EditableReview;
};

export default function ReviewsEdit({ project, sprints, review }: Props) {
    return (
        <>
            <Head title={`Edit review - ${project.name}`} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Heading
                    title="Edit review"
                    description={`Update the review of ${project.name}`}
                />

                <ReviewForm
                    projectId={project.id}
                    sprints={sprints}
                    review={review}
                />

                <Button variant="ghost" asChild>
                    <Link href={show([project.id, review.id])}>Cancel</Link>
                </Button>
            </div>
        </>
    );
}

ReviewsEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Reviews', href: index(props.project.id) },
        {
            title: 'Edit',
            href: show([props.project.id, props.review.id]),
        },
    ],
});
