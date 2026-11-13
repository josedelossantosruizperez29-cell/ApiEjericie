<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WeightRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'weight_kg' => 'required|numeric|between:1,500',
            'measured_at' => 'required|date|before_or_equal:now',
        ];
    }
}
