import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import SprintForm from '@/components/sprints/sprint-form';
import { Button } from '@/components/ui/button';
import { edit as editRoute, index, show } from '@/routes/sprints';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';

type Props = {
    project: { id: number; name: string; archived_at: string | null };
    sprint: {
        id: number;
        name: string;
        goal: string | null;
        starts_at: string;
        ends_at: string;
    };
};

export default function SprintsEdit({ project, sprint }: Props) {
    return (
        <>
            <Head title={`Edit ${sprint.name}`} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Heading
                    title={`Edit ${sprint.name}`}
                    description={`Update this sprint of ${project.name}`}
                />

                <SprintForm
                    projectId={project.id}
                    sprint={{
                        ...sprint,
                        goal: sprint.goal ?? '',
                    }}
                />

                <Button variant="ghost" asChild>
                    <Link href={show([project.id, sprint.id])}>Cancel</Link>
                </Button>
            </div>
        </>
    );
}

SprintsEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Sprints', href: index(props.project.id) },
        {
            title: props.sprint.name,
            href: show([props.project.id, props.sprint.id]),
        },
        {
            title: 'Edit',
            href: editRoute([props.project.id, props.sprint.id]),
        },
    ],
});
