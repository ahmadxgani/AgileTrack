import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import RoleOptions, { type RoleOption } from '@/components/admin/role-options';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as editRoute, index, update } from '@/routes/admin/users';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AdminUser } from './index';

type Props = {
    user: AdminUser;
    roles: RoleOption[];
};

export default function UsersEdit({ user, roles }: Props) {
    const [selectedRoles, setSelectedRoles] = useState<string[]>(
        user.roles.map((role) => role.value),
    );
    const [confirmingDeactivation, setConfirmingDeactivation] = useState(false);

    const form = useForm({
        name: user.name,
        email: user.email,
        password: '',
        roles: user.roles.map((role) => role.value),
        is_active: user.is_active,
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
        form.patch(update(user.id).url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="max-w-xl space-y-6 p-4">
                <div className="space-y-2">
                    <Heading
                        title="Edit account"
                        description="Update the account details, roles and access status"
                    />
                    <Badge variant={user.is_active ? 'default' : 'outline'}>
                        {user.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </div>

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
                        <Label htmlFor="password">
                            New password (optional)
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            minLength={8}
                            autoComplete="new-password"
                        />
                        <p className="text-sm text-muted-foreground">
                            Leave blank to keep the current password.
                        </p>
                        <InputError message={form.errors.password} />
                    </div>

                    <RoleOptions
                        options={roles}
                        selected={selectedRoles}
                        error={form.errors.roles}
                        onToggle={toggleRole}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="is_active">Account status</Label>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) => {
                                    if (checked === true) {
                                        form.setData('is_active', true);
                                    } else {
                                        setConfirmingDeactivation(true);
                                    }
                                }}
                            />
                            <Label htmlFor="is_active" className="font-normal">
                                Account can sign in
                            </Label>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Deactivating an account immediately blocks sign in.
                            The system always keeps at least one active
                            administrator.
                        </p>
                        <InputError message={form.errors.is_active} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button disabled={form.processing}>Save changes</Button>
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>

            <Dialog
                open={confirmingDeactivation}
                onOpenChange={setConfirmingDeactivation}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Deactivate this account?</DialogTitle>
                        <DialogDescription>
                            {user.name} will be signed out and unable to sign in
                            until the account is reactivated.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmingDeactivation(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                form.setData('is_active', false);
                                setConfirmingDeactivation(false);
                            }}
                        >
                            Deactivate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

UsersEdit.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
        {
            title: 'Edit account',
            href: editRoute(0),
        },
    ],
};
