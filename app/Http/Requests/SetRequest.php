<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expected_exercise_id' => 'sometimes|required|uuid',
            'workout_exercise_id' => 'required|uuid',
            'set_number' => 'required|integer|min:1|max:100',
            'reps' => 'sometimes|integer|between:1,1000',
            'duration_seconds' => 'sometimes|integer|between:1,86400',
            'distance_m' => 'sometimes|numeric|between:0.001,100000',
            'load_kg' => 'sometimes|nullable|numeric|between:0,1000',
            'perceived_effort' => 'sometimes|nullable|integer|between:1,10',
        ];
    }
}
