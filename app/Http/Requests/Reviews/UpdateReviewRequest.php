<?php

namespace App\Http\Requests\Reviews;

use App\Models\Project;
use App\Models\SprintReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The full edit form, which a manager fills.
 *
 * A project member may reach the same endpoint to record feedback only, so
 * the request is authorized by either ability and the controller applies
 * just the fields the actor may change.
 */
class UpdateReviewRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var SprintReview $review */
        $review = $this->route('review');

        return Gate::allows('update', $review)
            || Gate::allows('recordFeedback', $review);
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
