<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine whether the user is allowed to make this request.
     *
     * Checked before validation so a non-administrator always gets 403,
     * never a 422 that reveals the validation rules.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_administrator;
    }

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
            // Optional here: a blank password keeps the current one.
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
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

                    return;
                }

                $keepsActiveAdministrator = $this->boolean('is_active')
                    && in_array(UserRole::Administrator->value, (array) $this->input('roles', []), true);

                if (! $keepsActiveAdministrator && $account->isLastActiveAdministrator()) {
                    $validator->errors()->add(
                        'roles',
                        'The system must keep at least one active administrator.',
                    );
                }
            },
        ];
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
