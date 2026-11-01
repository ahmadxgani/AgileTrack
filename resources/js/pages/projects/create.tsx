import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import ProjectForm from '@/components/projects/project-form';
import { Button } from '@/components/ui/button';
import { create as createRoute, index } from '@/routes/projects';

type Props = {
    statuses: { value: string; label: string }[];
};

export default function ProjectsCreate({ statuses }: Props) {
    return (
        <>
            <Head title="New project" />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Heading
                    title="New project"
                    description="Projects you create list you as their manager"
                />

                <ProjectForm statuses={statuses} />

                <Button variant="ghost" asChild>
                    <Link href={index()}>Cancel</Link>
                </Button>
            </div>
        </>
    );
}

ProjectsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: 'New project',
            href: createRoute(),
        },
    ],
};
