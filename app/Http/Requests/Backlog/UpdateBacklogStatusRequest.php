<?php

namespace App\Http\Requests\Backlog;

use App\Enums\BacklogStatus;
use App\Models\BacklogItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The status-only update an assigned team member may make.
 */
class UpdateBacklogStatusRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var BacklogItem $item */
        $item = $this->route('backlogItem');

        return Gate::allows('updateStatus', $item);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(BacklogStatus::class)],
        ];
    }
}
