<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
     * Normalize the input before validation.
     *
     * A partial update may send only one of the two dates. The stored
     * counterpart is merged in so the after_or_equal rule always compares
     * two real dates, in both directions and whatever format the client
     * sends.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn ($value) => $value === '' ? null : $value,
            $this->only(['description', 'starts_at', 'ends_at']),
        ));

        /** @var Project $project */
        $project = $this->route('project');

        if (! $this->exists('starts_at')) {
            $this->merge(['starts_at' => $project->starts_at?->format('Y-m-d')]);
        }

        if (! $this->exists('ends_at')) {
            $this->merge(['ends_at' => $project->ends_at?->format('Y-m-d')]);
        }
    }
}
