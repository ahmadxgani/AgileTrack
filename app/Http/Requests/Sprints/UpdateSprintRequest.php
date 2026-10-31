<?php

namespace App\Http\Requests\Sprints;

use App\Models\Sprint;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSprintRequest extends FormRequest
{
    /**
     * Determine whether the user is allowed to make this request.
     */
    public function authorize(): bool
    {
        /** @var Sprint $sprint */
        $sprint = $this->route('sprint');

        return Gate::allows('update', $sprint);
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
            'goal' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * Normalize the input before validation.
     *
     * A partial update may send only one of the two dates. The stored
     * counterpart is merged in so the after_or_equal rule always compares
     * two real dates, in both directions. A send without a name keeps the
     * stored one, so a date-only change never has to repeat it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn ($value) => $value === '' ? null : $value,
            $this->only(['goal', 'starts_at', 'ends_at']),
        ));

        /** @var Sprint $sprint */
        $sprint = $this->route('sprint');

        if (! $this->exists('name')) {
            $this->merge(['name' => $sprint->name]);
        }

        if (! $this->exists('starts_at')) {
            $this->merge(['starts_at' => $sprint->starts_at->format('Y-m-d')]);
        }

        if (! $this->exists('ends_at')) {
            $this->merge(['ends_at' => $sprint->ends_at->format('Y-m-d')]);
        }
    }
}
