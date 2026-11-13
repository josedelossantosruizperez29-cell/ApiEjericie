<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EquipmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'equipment_ids' => 'present|array|list|max:100',
            'equipment_ids.*' => 'required|uuid|distinct|exists:equipment,id',
        ];
    }
}
