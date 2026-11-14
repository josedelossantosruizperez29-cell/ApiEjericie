<?php

namespace App\Services\AI;

use App\Models\FoodItem;
use App\Services\NutritionTotals;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NutritionAssistant
{
    public function __construct(private GeminiClient $client, private NutritionTotals $totals) {}

    public function analyze(#[\SensitiveParameter] string $description, ?string $mealType = null): array
    {
        $references = FoodItem::where('verification', 'verified')->where('reference_unit', 'g')->orderBy('slug')->limit(200)->get()->keyBy('slug');
        $schema = json_decode(file_get_contents(resource_path('schemas/meal-interpretation.json')), true, flags: JSON_THROW_ON_ERROR);
        [$text] = $this->client->generate('Extrae solo alimentos, cantidades, unidades, preparación y tipo de comida explícitos en el texto. Usa reference_slug solo si la preparación y el tipo coinciden sin ambigüedad. No asumas variedades, tamaño, peso por pieza ni receta. Señala ambigüedad o información faltante en missing. Omite los campos opcionales desconocidos. Nunca inventes nutrientes.', [
            'description' => $description, 'references' => $references->values()->map->only(['slug', 'name'])->all(),
        ], $schema, 2048, 1);
        $mealTypes = 'in:breakfast,lunch,dinner,snack';
        $v = Validator::make(['result' => json_decode($text, true)], [
            'result' => 'required|array:meal_type,items', 'result.meal_type' => ['sometimes', $mealTypes],
            'result.items' => 'present|array|list|max:50', 'result.items.*' => 'array:name,quantity,unit,preparation,reference_slug,missing',
            'result.items.*.name' => 'required|string|max:150', 'result.items.*.quantity' => 'sometimes|numeric|gt:0|max:10000',
            'result.items.*.unit' => 'sometimes|string|max:30', 'result.items.*.preparation' => 'sometimes|string|max:150',
            'result.items.*.reference_slug' => 'sometimes|string|max:150', 'result.items.*.missing' => 'sometimes|array|list|max:10',
            'result.items.*.missing.*' => 'string|max:150',
        ]);
        if ($v->fails()) {
            throw new HttpException(502, 'invalid_response');
        }
        $result = $v->validated()['result'];
        $rows = [];
        foreach ($result['items'] as $item) {
            $missing = $item['missing'] ?? [];
            $food = $references->get($item['reference_slug'] ?? '');
            $factor = match (mb_strtolower(trim($item['unit'] ?? ''))) {
                'g','gramo','gramos' => 1,'kg','kilogramo','kilogramos' => 1000,default => null
            };
            $grams = isset($item['quantity'],$factor) ? $factor * $item['quantity'] : null;
            if (! $food) {
                $missing[] = 'Aclarar alimento y preparación para seleccionar una referencia.';
            }
            if (! $grams || $grams > 10000) {
                $missing[] = 'Confirmar la cantidad comestible en gramos.';
            }
            $ready = empty($missing);
            unset($item['reference_slug'],$item['missing']);
            $rows[] = $item + ['missing' => $missing, 'uncertain' => ! $ready, 'grams' => $grams, 'reference' => $food?->only(['id', 'slug', 'name', 'source', 'source_date', 'reference_unit', 'reference_quantity']),
                'nutrients' => $this->totals->portion($ready ? $food : null, $grams ?? 0),
                'confirmation' => $ready ? ['food_item_id' => $food->id, 'quantity' => $grams, 'unit' => 'g'] : null];
        }

        return ['meal_type' => $mealType ?? $result['meal_type'] ?? null, 'items' => $rows, 'requires_confirmation' => true];
    }

    public function interpret(#[\SensitiveParameter] string $description): array
    {
        return $this->analyze($description)['items'];
    }
}
