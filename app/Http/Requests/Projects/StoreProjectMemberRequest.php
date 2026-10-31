<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectMemberRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return Gate::allows('manageMembers', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'project_role' => ['required', Rule::enum(ProjectRole::class)],
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
                if ($validator->errors()->has('email')) {
                    return;
                }

                /** @var Project $project */
                $project = $this->route('project');

                $user = User::query()
                    ->where('email', mb_strtolower($this->string('email')->toString()))
                    ->where('is_active', true)
                    ->first();

                if ($user === null) {
                    $validator->errors()->add('email', 'No active account uses that email address.');

                    return;
                }

                if ($project->memberships()->where('user_id', $user->id)->exists()) {
                    $validator->errors()->add('email', 'That account is already a member of this project.');
                }
            },
        ];
    }
}
