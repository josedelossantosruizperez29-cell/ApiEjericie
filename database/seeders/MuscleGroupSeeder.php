<?php

namespace Database\Seeders;

use App\Models\MuscleGroup;
use Illuminate\Database\Seeder;

class MuscleGroupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'pecho' => 'Pectorales',
            'triceps' => 'Tríceps',
            'espalda' => 'Dorsales y espalda media',
            'biceps' => 'Bíceps',
            'hombros' => 'Deltoides',
            'cuadriceps' => 'Cuádriceps',
            'gluteos' => 'Glúteos',
            'core' => 'Abdominales y estabilizadores',
            'gemelos' => 'Pantorrillas',
            'isquios' => 'Isquiotibiales',
            'cadera' => 'Flexores de cadera',
        ] as $slug => $name) {
            MuscleGroup::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
