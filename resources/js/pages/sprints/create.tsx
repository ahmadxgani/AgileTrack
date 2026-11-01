import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import SprintForm from '@/components/sprints/sprint-form';
import { Button } from '@/components/ui/button';
import { create as createRoute, index } from '@/routes/sprints';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';

type Props = {
    project: { id: number; name: string; archived_at: string | null };
};

export default function SprintsCreate({ project }: Props) {
    return (
        <>
            <Head title={`New sprint - ${project.name}`} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Heading
                    title="New sprint"
                    description={`Plan a time box for ${project.name}`}
                />

                <SprintForm projectId={project.id} />

                <Button variant="ghost" asChild>
                    <Link href={index(project.id)}>Cancel</Link>
                </Button>
            </div>
        </>
    );
}

SprintsCreate.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Sprints', href: index(props.project.id) },
        { title: 'New sprint', href: createRoute(props.project.id) },
    ],
});
