<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreferenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'units' => 'sometimes|in:metric,imperial',
            'recovery' => 'sometimes|boolean',
            'daily_cutoff' => 'sometimes|date_format:H:i',
            'exercise_preferences' => 'sometimes|string|max:500',
            'penalty_adjustments' => 'sometimes|boolean',
        ];
    }
}
