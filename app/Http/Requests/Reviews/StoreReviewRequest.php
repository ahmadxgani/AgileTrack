<?php

namespace App\Http\Requests\Reviews;

use App\Models\Project;
use App\Models\SprintReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return Gate::allows('create', [SprintReview::class, $project]);
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
            // A review either covers one sprint of this project or is a
            // general project review.
            'sprint_id' => [
                'nullable',
                'integer',
                Rule::exists('sprints', 'id')->where('project_id', $project->id),
            ],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'feedback' => ['nullable', 'string'],
        ];
    }
}
