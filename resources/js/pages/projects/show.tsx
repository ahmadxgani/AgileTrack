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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as backlogIndex } from '@/routes/projects/backlog';
import { index as sprintsIndex } from '@/routes/sprints';
import { archive, edit as editRoute, index, show } from '@/routes/projects';
import { destroy, store, update } from '@/routes/projects/members';

export type ProjectMember = {
    membership_id: number;
    id: number;
    name: string;
    email: string;
    project_role: string;
    project_role_label: string;
};

export type ProjectDetail = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    starts_at: string | null;
    ends_at: string | null;
    archived_at: string | null;
    creator: string | null;
    members: ProjectMember[];
};

type Props = {
    project: ProjectDetail;
    roles: { value: string; label: string }[];
    can: { update: boolean; manage_members: boolean };
};

export default function ProjectsShow({ project, roles, can }: Props) {
    const [confirmingArchive, setConfirmingArchive] = useState(false);
    const [confirmingRemoval, setConfirmingRemoval] = useState<number | null>(
        null,
    );
    // Role changes are optimistic; a failed request falls back to the value
    // the server last sent, which the Select cannot do on its own.
    const [pendingRoles, setPendingRoles] = useState<Record<number, string>>(
        {},
    );
    const { errors } = usePage().props;

    const memberRole = roles.find((role) => role.value === 'member');

    const addMember = useForm({
        email: '',
        project_role: memberRole?.value ?? roles[0]?.value ?? 'member',
    });

    function submitMember(event: React.FormEvent) {
        event.preventDefault();
        addMember.post(store(project.id).url, {
            preserveScroll: true,
            onSuccess: () => addMember.reset('email'),
        });
    }

    function changeRole(member: ProjectMember, value: string) {
        setPendingRoles((current) => ({
            ...current,
            [member.membership_id]: value,
        }));

        router.patch(
            update([project.id, member.membership_id]).url,
            { project_role: value },
            {
                preserveScroll: true,
                // The choice shows immediately; once the request settles the
                // server value takes over again, so a rejected change reverts.
                onFinish: () =>
                    setPendingRoles((current) => {
                        const next = { ...current };
                        delete next[member.membership_id];

                        return next;
                    }),
            },
        );
    }

    function removeMember(member: ProjectMember) {
        router.delete(destroy([project.id, member.membership_id]).url, {
            preserveScroll: true,
            onFinish: () => setConfirmingRemoval(null),
        });
    }

    function archiveProject() {
        router.patch(archive(project.id).url, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title={project.name} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading title={project.name} />

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                {project.status_label}
                            </Badge>
                            {project.archived_at && (
                                <Badge variant="outline">Archived</Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={sprintsIndex(project.id)}>Sprints</Link>
                        </Button>

                        <Button variant="outline" asChild>
                            <Link href={backlogIndex(project.id)}>Backlog</Link>
                        </Button>

                        {can.update && !project.archived_at && (
                            <Button variant="outline" asChild>
                                <Link href={editRoute(project.id)}>Edit</Link>
                            </Button>
                        )}

                        {can.update && !project.archived_at && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirmingArchive(true)}
                            >
                                Archive
                            </Button>
                        )}

                        <Button variant="ghost" asChild>
                            <Link href={index()}>Back to projects</Link>
                        </Button>
                    </div>
                </div>

                {confirmingArchive && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Archive this project?</CardTitle>
                            <CardDescription>
                                Archived projects stay visible but can no longer
                                be edited, and their members cannot be changed.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex gap-2">
                            <Button onClick={archiveProject}>Archive</Button>
                            <Button
                                variant="ghost"
                                onClick={() => setConfirmingArchive(false)}
                            >
                                Cancel
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <span className="text-muted-foreground">
                                Starts
                            </span>
                            <p>{project.starts_at ?? 'Not set'}</p>
                        </div>
                        <div>
                            <span className="text-muted-foreground">Ends</span>
                            <p>{project.ends_at ?? 'Not set'}</p>
                        </div>
                        <div className="sm:col-span-2">
                            <span className="text-muted-foreground">
                                Description
                            </span>
                            <p className="whitespace-pre-line">
                                {project.description ?? 'No description yet.'}
                            </p>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Created by
                            </span>
                            <p>{project.creator ?? 'Unknown'}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Members</CardTitle>
                        <CardDescription>
                            Managers edit the project; members take part in the
                            work; viewers follow along.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Project role</TableHead>
                                    {can.manage_members && (
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {project.members.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={can.manage_members ? 4 : 3}
                                            className="text-center text-muted-foreground"
                                        >
                                            No members yet.
                                        </TableCell>
                                    </TableRow>
                                )}

                                {project.members.map((member) => (
                                    <TableRow key={member.membership_id}>
                                        <TableCell className="font-medium">
                                            {member.name}
                                        </TableCell>
                                        <TableCell>{member.email}</TableCell>
                                        <TableCell>
                                            {can.manage_members ? (
                                                <Select
                                                    value={
                                                        pendingRoles[
                                                            member.membership_id
                                                        ] ?? member.project_role
                                                    }
                                                    onValueChange={(value) =>
                                                        changeRole(
                                                            member,
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        size="sm"
                                                        aria-label={`Project role for ${member.name}`}
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {roles.map((role) => (
                                                            <SelectItem
                                                                key={role.value}
                                                                value={
                                                                    role.value
                                                                }
                                                            >
                                                                {role.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Badge variant="secondary">
                                                    {member.project_role_label}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        {can.manage_members && (
                                            <TableCell className="text-right">
                                                {confirmingRemoval ===
                                                member.membership_id ? (
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label={`Confirm removing ${member.name}`}
                                                            onClick={() =>
                                                                removeMember(
                                                                    member,
                                                                )
                                                            }
                                                        >
                                                            Confirm
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setConfirmingRemoval(
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setConfirmingRemoval(
                                                                member.membership_id,
                                                            )
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {/* One slot for every project_role rejection: add,
                            role change and removal all report it here. The
                            add-member form must not render a second copy. */}
                        <InputError message={errors.project_role} />

                        {can.manage_members && (
                            <form
                                onSubmit={submitMember}
                                className="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="member-email">
                                        Add member by email
                                    </Label>
                                    <Input
                                        id="member-email"
                                        type="email"
                                        value={addMember.data.email}
                                        onChange={(event) =>
                                            addMember.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="name@example.com"
                                        required
                                    />
                                    <InputError
                                        message={addMember.errors.email}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="member-role">Role</Label>
                                    <Select
                                        value={addMember.data.project_role}
                                        onValueChange={(value) =>
                                            addMember.setData(
                                                'project_role',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="member-role">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button disabled={addMember.processing}>
                                    Add member
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ProjectsShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: index(),
        },
        {
            title: props.project.name,
            href: show(props.project.id),
        },
    ],
});
