<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $account */
        $account = $this->route('user');

        return [
            ...$this->profileRules($account->id),
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $account */
                $account = $this->route('user');

                if (! $this->boolean('is_active') && $account->is($this->user())) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }

                $keepsActiveAdministrator = $this->boolean('is_active')
                    && in_array(UserRole::Administrator->value, (array) $this->input('roles', []), true);

                if (! $keepsActiveAdministrator && $this->isLastActiveAdministrator($account)) {
                    $validator->errors()->add(
                        'roles',
                        'The system must keep at least one active administrator.',
                    );
                }
            },
        ];
    }

    /**
     * Determine whether the account is the only active administrator left.
     */
    private function isLastActiveAdministrator(User $account): bool
    {
        $isActiveAdministrator = $account->is_active
            && $account->roles()->where('role', UserRole::Administrator->value)->exists();

        if (! $isActiveAdministrator) {
            return false;
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('role', UserRole::Administrator->value))
            ->count() === 1;
    }

    /**
     * Normalize the input before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower($this->email)]);
        }
    }
}
