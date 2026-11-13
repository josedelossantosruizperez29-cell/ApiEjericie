<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReminderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kind' => 'required|in:breakfast,lunch,dinner,training',
            'timezone' => 'required|timezone:all',
            'local_time' => 'required|date_format:H:i',
            'enabled' => 'required|boolean',
        ];
    }
}
