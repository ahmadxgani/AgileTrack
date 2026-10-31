import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import ReviewForm from '@/components/reviews/review-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';
import { create as createRoute, index } from '@/routes/projects/reviews';

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    sprints: { id: number; name: string }[];
};

export default function ReviewsCreate({ project, sprints }: Props) {
    return (
        <>
            <Head title={`New review - ${project.name}`} />

            <div className="space-y-6 p-4">
                <div className="space-y-2">
                    <Heading
                        title="Schedule a review"
                        description={`Plan the next sprint review of ${project.name}`}
                    />

                    {project.archived_at && (
                        <Badge variant="outline">
                            Archived project, read only
                        </Badge>
                    )}
                </div>

                <ReviewForm projectId={project.id} sprints={sprints} />

                <Button variant="ghost" asChild>
                    <Link href={index(project.id)}>Cancel</Link>
                </Button>
            </div>
        </>
    );
}

ReviewsCreate.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Reviews', href: index(props.project.id) },
        { title: 'New review', href: createRoute(props.project.id) },
    ],
});
