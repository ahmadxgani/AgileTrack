<?php

namespace App\Http\Requests\Reviews;

use App\Enums\ReviewDecision;
use App\Models\SprintReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DecideReviewRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var SprintReview $review */
        $review = $this->route('review');

        return Gate::allows('decide', $review);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The follow-up title is only needed when the review has no linked item
     * yet, which the model checks once it holds the row lock.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'follow_up_title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
