import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import BacklogItemForm from '@/components/backlog/backlog-item-form';
import { Button } from '@/components/ui/button';
import { edit as editRoute, index } from '@/routes/projects/backlog';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';

type Props = {
    project: { id: number; name: string };
    item: {
        id: number;
        title: string;
        description: string | null;
        priority: string;
        status: string;
        assignees: number[];
    };
    members: { id: number; name: string }[];
    priorities: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
};

export default function BacklogEdit({
    project,
    item,
    members,
    priorities,
    statuses,
}: Props) {
    return (
        <>
            <Head title={`Edit ${item.title}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title={`Edit ${item.title}`}
                    description={`Update this item in the ${project.name} backlog`}
                />

                <BacklogItemForm
                    projectId={project.id}
                    item={{
                        ...item,
                        description: item.description ?? '',
                    }}
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

BacklogEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: props.project.name, href: projectShow(props.project.id) },
        { title: 'Backlog', href: index(props.project.id) },
        {
            title: 'Edit item',
            href: editRoute([props.project.id, props.item.id]),
        },
    ],
});
