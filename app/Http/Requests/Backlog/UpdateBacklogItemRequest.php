<?php

namespace App\Http\Requests\Backlog;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Models\BacklogItem;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateBacklogItemRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var BacklogItem $item */
        $item = $this->route('backlogItem');

        return Gate::allows('update', $item);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::enum(BacklogPriority::class)],
            'status' => ['required', Rule::enum(BacklogStatus::class)],
            'assignees' => ['sometimes', 'array'],
            // Only project members may be assigned; anyone else is rejected
            // here rather than silently dropped.
            'assignees.*' => [
                'integer',
                'distinct',
                Rule::exists('project_memberships', 'user_id')
                    ->where('project_id', $project->id),
            ],
        ];
    }
}
