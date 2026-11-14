<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeMealRequest extends FormRequest
{
    public function rules(): array
    {
        return ['description' => 'required|string|max:1000', 'meal_type' => 'sometimes|in:breakfast,lunch,dinner,snack'];
    }
}
