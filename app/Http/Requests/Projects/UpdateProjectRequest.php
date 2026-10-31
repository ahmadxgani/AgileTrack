<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return Gate::allows('update', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * A partial update may send only one of the two dates, so the pair is
     * also checked against the stored value of the other one.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
                    return;
                }

                /** @var Project $project */
                $project = $this->route('project');

                $startsAt = $this->exists('starts_at')
                    ? $this->input('starts_at')
                    : $project->starts_at?->format('Y-m-d');
                $endsAt = $this->exists('ends_at')
                    ? $this->input('ends_at')
                    : $project->ends_at?->format('Y-m-d');

                if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
                    $validator->errors()->add('ends_at', 'The end date must not be before the start date.');
                }
            },
        ];
    }

    /**
     * Normalize the input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn ($value) => $value === '' ? null : $value,
            $this->only(['description', 'starts_at', 'ends_at']),
        ));
    }
}
