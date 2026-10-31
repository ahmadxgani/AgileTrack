import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import RoleOptions, { type RoleOption } from '@/components/admin/role-options';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create as createRoute, index, store } from '@/routes/admin/users';

type Props = {
    roles: RoleOption[];
};

export default function UsersCreate({ roles }: Props) {
    const [selectedRoles, setSelectedRoles] = useState<string[]>([]);

    const form = useForm({
        name: '',
        email: '',
        password: '',
        roles: [] as string[],
    });

    function toggleRole(value: string) {
        const next = selectedRoles.includes(value)
            ? selectedRoles.filter((role) => role !== value)
            : [...selectedRoles, value];

        setSelectedRoles(next);
        form.setData('roles', next);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(store().url);
    }

    return (
        <>
            <Head title="New account" />

            <div className="max-w-xl space-y-6 p-4">
                <Heading
                    title="New account"
                    description="Create an account with an initial password and one or more roles"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoComplete="name"
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            required
                            autoComplete="email"
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            required
                            minLength={8}
                            autoComplete="new-password"
                        />
                        <p className="text-sm text-muted-foreground">
                            Share this password with the account holder through
                            a secure channel. No email is sent.
                        </p>
                        <InputError message={form.errors.password} />
                    </div>

                    <RoleOptions
                        options={roles}
                        selected={selectedRoles}
                        error={form.errors.roles}
                        onToggle={toggleRole}
                    />

                    <div className="flex items-center gap-3">
                        <Button disabled={form.processing}>
                            Create account
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

UsersCreate.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
        {
            title: 'New account',
            href: createRoute(),
        },
    ],
};
