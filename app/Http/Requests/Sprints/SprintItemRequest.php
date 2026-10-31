<?php

namespace App\Http\Requests\Sprints;

use App\Models\Sprint;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Names the backlog item an attach or detach call acts on.
 *
 * Only the shape is validated here. Whether the item may join this sprint,
 * or already belongs to it, depends on state the controller re-reads under
 * a lock, so it reports those answers as validation errors itself.
 */
class SprintItemRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var Sprint $sprint */
        $sprint = $this->route('sprint');

        return Gate::allows('manageItems', $sprint);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'backlog_item_id' => ['required', 'integer'],
        ];
    }
}
