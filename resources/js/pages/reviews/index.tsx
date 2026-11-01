import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';
import { create as createRoute, index, show } from '@/routes/projects/reviews';

export type ReviewRow = {
    id: number;
    sprint: string | null;
    scheduled_at: string | null;
    decision: string;
    decision_label: string;
    decided_by: string | null;
    follow_up: string | null;
};

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    reviews: ReviewRow[];
    can: { create: boolean };
};

export default function ReviewsIndex({ project, reviews, can }: Props) {
    return (
        <>
            <Head title={`${project.name} reviews`} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title="Reviews"
                            description={`Sprint reviews of ${project.name}`}
                        />

                        {project.archived_at && (
                            <Badge variant="outline">
                                Archived project, read only
                            </Badge>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {can.create && (
                            <Button asChild>
                                <Link href={createRoute(project.id)}>
                                    Schedule review
                                </Link>
                            </Button>
                        )}

                        <Button variant="ghost" asChild>
                            <Link href={projectShow(project.id)}>
                                Back to project
                            </Link>
                        </Button>
                    </div>
                </div>

                {reviews.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No reviews yet</CardTitle>
                            <CardDescription>
                                {can.create
                                    ? 'Schedule a review to look back on the sprint with the team.'
                                    : 'This project has no reviews yet.'}
                            </CardDescription>
                        </CardHeader>
                        {can.create && (
                            <CardContent>
                                <Button asChild>
                                    <Link href={createRoute(project.id)}>
                                        Schedule review
                                    </Link>
                                </Button>
                            </CardContent>
                        )}
                    </Card>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Scheduled</TableHead>
                                    <TableHead>Sprint</TableHead>
                                    <TableHead>Decision</TableHead>
                                    <TableHead>Decided by</TableHead>
                                    <TableHead>Follow-up</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reviews.map((review) => (
                                    <TableRow key={review.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={show([
                                                    project.id,
                                                    review.id,
                                                ])}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {review.scheduled_at ??
                                                    'Not scheduled'}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {review.sprint ?? 'Whole project'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    review.decision ===
                                                    'approved'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {review.decision_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {review.decided_by ?? 'Undecided'}
                                        </TableCell>
                                        <TableCell>
                                            {review.follow_up ?? 'None'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

ReviewsIndex.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Reviews', href: index(props.project.id) },
    ],
});
