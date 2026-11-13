<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'birth_date' => 'sometimes|date_format:Y-m-d|before_or_equal:today|after:1900-01-01|prohibits:age_years',
            'age_years' => 'sometimes|integer|between:1,120|prohibits:birth_date',
            'weight_kg' => 'sometimes|numeric|between:1,500',
            'height_cm' => 'sometimes|numeric|between:40,260',
            'timezone' => 'sometimes|timezone:all',
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'goal' => 'sometimes|in:fat_loss,muscle_gain,strength_cardio',
            'weekly_availability' => 'sometimes|array|list|min:1|max:7',
            'session_minutes' => 'sometimes|integer|between:5,180',
            'step' => 'sometimes|integer|between:0,50',
            'complete' => 'sometimes|boolean',
            'equipment_ids' => 'sometimes|array|list|max:100',
            'exercise_preferences' => 'sometimes|string|max:500',
            'weekly_availability.*' => 'required|integer|between:1,7|distinct',
            'equipment_ids.*' => 'required|uuid|distinct|exists:equipment,id',
        ];
    }
}
