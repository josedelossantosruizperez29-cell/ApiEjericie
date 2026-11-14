<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use Illuminate\Database\Seeder;

class FoodItemSeeder extends Seeder
{
    public function run(): void
    {
        // USDA SR Legacy, values per 100 g of edible portion in the stated preparation.
        foreach ([
            [168878, 'Arroz blanco de grano largo enriquecido cocido', 130, 2.69, 28.17, 0.28],
            [169756, 'Arroz blanco de grano largo crudo sin enriquecer', 365, 7.13, 79.95, 0.66],
            [173424, 'Huevo entero cocido duro', 155, 12.58, 1.12, 10.61],
            [171477, 'Pechuga de pollo asada sin piel', 165, 31.02, 0, 3.57],
            [169997, 'Cilantro fresco crudo', 23, 2.13, 3.67, 0.52],
        ] as [$id,$name,$kcal,$protein,$carbs,$fat]) {
            FoodItem::firstOrCreate(['slug' => 'usda-'.$id], [
                'name' => $name, 'reference_quantity' => 100, 'reference_unit' => 'g', 'source_date' => '2019-04-01',
                'source' => "https://fdc.nal.usda.gov/food-details/$id/nutrients", 'verification' => 'verified',
                'kcal' => $kcal, 'protein_g' => $protein, 'carbs_g' => $carbs, 'fat_g' => $fat,
            ]);
        }
    }
}
