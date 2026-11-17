<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'muscle_group' => 'sometimes|string|max:100',
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'category' => 'sometimes|in:machine,bodyweight,cardio',
            'measurement' => 'sometimes|in:reps,time,distance',
            'equipment_id' => 'sometimes|uuid',
            'eligible' => 'sometimes|boolean',
            'training_mode' => 'sometimes|in:machine,bodyweight',
        ];
    }
}
