<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'request_key' => 'required|uuid',
        ];
    }
}
