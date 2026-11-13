<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
            'reason' => 'required|string|max:1000',
        ];
    }
}
