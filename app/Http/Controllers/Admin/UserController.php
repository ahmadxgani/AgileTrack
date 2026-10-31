<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List the accounts the current administrator can manage.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => $this->toRow($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new account.
     */
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', [
            'roles' => $this->roleOptions(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Store a newly created account.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        DB::transaction(function () use ($request): void {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
                'is_active' => true,
            ]);

            // No verification email can be delivered yet, so an administrator
            // creates accounts that are already verified.
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->syncRoles($user, $request->array('roles'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account created.']);

        return to_route('admin.users.index');
    }

    /**
     * Show the form for editing an account.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/edit', [
            'user' => $this->toRow($user->load('roles')),
            'roles' => $this->roleOptions(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update an account, including its roles and active status.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        DB::transaction(function () use ($request, $user): void {
            // The account row is locked before its roles are read, so two
            // administrators demoting each other at the same moment queue up
            // instead of both seeing the other and both succeeding.
            $account = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            // Both checks exist for the same rule at different moments. The
            // request rule runs before the transaction and answers with a
            // friendly message; this one runs under the account lock and is
            // the authoritative gate, because a concurrent administrator's
            // demotion blocks on the row lock and commits between the two.
            $this->ensureAnActiveAdministratorRemains(
                $account,
                $request->boolean('is_active')
                    && in_array(UserRole::Administrator->value, (array) $request->input('roles', []), true),
            );

            $account->fill([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'is_active' => $request->boolean('is_active'),
            ]);

            // Admin-managed accounts stay verified until email delivery exists.
            if ($request->filled('password')) {
                $account->password = $request->string('password')->toString();
            }

            $account->save();

            $this->syncRoles($account, $request->array('roles'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account updated.']);

        return to_route('admin.users.edit', $user);
    }

    /**
     * Refuse a change that would leave the system without an active
     * administrator.
     *
     * The caller holds the account row lock, so the read below cannot miss a
     * role or status change another administrator is writing right now. This
     * is the authoritative check; the request rule only produces the friendly
     * message before the transaction starts.
     *
     * @throws ValidationException
     */
    private function ensureAnActiveAdministratorRemains(User $account, bool $remainsActiveAdministrator): void
    {
        if ($remainsActiveAdministrator) {
            return;
        }

        if ($account->isLastActiveAdministrator()) {
            throw ValidationException::withMessages([
                'roles' => 'The system must keep at least one active administrator.',
            ]);
        }
    }

    /**
     * Replace the roles assigned to the account.
     *
     * @param  array<int, string>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        $user->roles()->delete();

        foreach (array_unique($roles) as $role) {
            $user->roles()->create(['role' => UserRole::from($role)]);
        }

        $user->unsetRelation('roles');
    }

    /**
     * Shape an account for the admin pages.
     *
     * @return array<string, mixed>
     */
    private function toRow(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles
                ->map(fn ($role): array => [
                    'value' => $role->role->value,
                    'label' => $role->role->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Get the selectable roles.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_map(
            fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()],
            UserRole::cases(),
        );
    }
}
