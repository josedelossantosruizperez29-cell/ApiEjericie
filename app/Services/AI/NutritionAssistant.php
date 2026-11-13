<?php

namespace App\Services\AI;

use App\Models\FoodItem;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NutritionAssistant
{
    public function __construct(private GeminiClient $client) {}

    public function interpret(#[\SensitiveParameter] string $description): array
    {
        Validator::make(['description' => $description], ['description' => 'required|string|max:1000'])->validate();
        $schema = ['type' => 'object', 'required' => ['items'], 'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['name'], 'properties' => ['name' => ['type' => 'string'], 'quantity' => ['type' => 'number'], 'unit' => ['type' => 'string']]]]]];
        [$text] = $this->client->generate('Extrae alimentos y cantidades explícitas de esta descripción. Omite campos desconocidos. Conserva el nombre del alimento en español.', ['description' => $description], $schema);
        $validator = Validator::make(['result' => json_decode($text, true)], ['result' => 'required|array:items', 'result.items' => 'present|array|list|max:50', 'result.items.*' => 'array:name,quantity,unit', 'result.items.*.name' => 'required|string|max:150', 'result.items.*.quantity' => 'sometimes|numeric|gt:0|max:10000', 'result.items.*.unit' => 'sometimes|string|max:30']);
        if ($validator->fails()) {
            throw new HttpException(502, 'invalid_response');
        }

        return array_map(function ($item) {
            $food = FoodItem::whereRaw('lower(name) = lower(?)', [$item['name']])->first();

            return $item + ['reference' => $food?->only(['id', 'name', 'reference_quantity', 'reference_unit', 'source', 'verification']), 'uncertain' => ! $food || ! isset($item['quantity']) || ($item['unit'] ?? null) !== $food->reference_unit];
        }, $validator->validated()['result']['items']);
    }
}
