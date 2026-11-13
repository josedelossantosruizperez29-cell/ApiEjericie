<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PortionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'food_item_id' => 'nullable|uuid|exists:food_items,id',
            'name' => 'required_without:food_item_id|string|max:150',
            'unit' => 'required|string|max:30',
            'quantity' => 'required|numeric|between:0.001,10000',
        ];
    }
}
