<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MealRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kind' => 'required|in:breakfast,lunch,dinner,snack',
            'eaten_at' => 'required|date|before_or_equal:now',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
