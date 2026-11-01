import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import BacklogItemForm from '@/components/backlog/backlog-item-form';
import { Button } from '@/components/ui/button';
import { create as createRoute, index } from '@/routes/projects/backlog';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';

type Props = {
    project: { id: number; name: string };
    members: { id: number; name: string }[];
    priorities: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
};

export default function BacklogCreate({
    project,
    members,
    priorities,
    statuses,
}: Props) {
    return (
        <>
            <Head title={`New backlog item - ${project.name}`} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Heading
                    title="New backlog item"
                    description={`Add work to the ${project.name} backlog`}
                />

                <BacklogItemForm
                    projectId={project.id}
                    members={members}
                    priorities={priorities}
                    statuses={statuses}
                />

                <Button variant="ghost" asChild>
                    <Link href={index(project.id)}>Cancel</Link>
                </Button>
            </div>
        </>
    );
}

BacklogCreate.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Backlog', href: index(props.project.id) },
        { title: 'New item', href: createRoute(props.project.id) },
    ],
});
