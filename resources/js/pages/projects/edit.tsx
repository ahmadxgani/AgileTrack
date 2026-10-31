import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import ProjectForm from '@/components/projects/project-form';
import { Button } from '@/components/ui/button';
import { edit as editRoute, index, show } from '@/routes/projects';

type Props = {
    project: {
        id: number;
        name: string;
        description: string | null;
        status: string;
        starts_at: string | null;
        ends_at: string | null;
    };
    statuses: { value: string; label: string }[];
};

export default function ProjectsEdit({ project, statuses }: Props) {
    return (
        <>
            <Head title={`Edit ${project.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title={`Edit ${project.name}`}
                    description="Update the plan for this project"
                />

                <ProjectForm
                    statuses={statuses}
                    project={{
                        ...project,
                        description: project.description ?? '',
                        starts_at: project.starts_at ?? '',
                        ends_at: project.ends_at ?? '',
                    }}
                />

                <Button variant="ghost" asChild>
                    <Link href={show(project.id)}>Cancel</Link>
                </Button>
            </div>
        </>
    );
}

ProjectsEdit.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: 'Edit project',
            href: editRoute(props.project.id),
        },
    ],
});
