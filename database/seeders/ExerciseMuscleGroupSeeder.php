<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseMuscleGroup;
use App\Models\MuscleGroup;
use Illuminate\Database\Seeder;

class ExerciseMuscleGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = MuscleGroup::pluck('id', 'slug');
        $exercises = Exercise::all()->keyBy('slug');
        foreach ([
            'press-sentado' => ['pecho', 'triceps', 'flexion-inclinada'],
            'mariposa' => ['pecho', 'hombros', 'flexion-inclinada'],
            'jalon' => ['espalda', 'biceps', null],
            'remo' => ['espalda', 'biceps', null],
            'extension-piernas' => ['cuadriceps', 'core', null],
            'curl-predicador' => ['biceps', 'core', null],
            'flexion' => ['pecho', 'triceps', 'flexion-rodillas'],
            'flexion-rodillas' => ['pecho', 'triceps', 'flexion-inclinada'],
            'flexion-inclinada' => ['pecho', 'triceps', null],
            'sentadilla' => ['cuadriceps', 'gluteos', null],
            'zancada' => ['cuadriceps', 'gluteos', 'zancada-atras'],
            'zancada-atras' => ['gluteos', 'cuadriceps', 'sentadilla'],
            'puente' => ['gluteos', 'isquios', null],
            'talones' => ['gemelos', 'core', null],
            'plancha' => ['core', 'hombros', 'dead-bug'],
            'plancha-lateral' => ['core', 'hombros', 'dead-bug'],
            'dead-bug' => ['core', 'cadera', null],
            'bird-dog' => ['core', 'gluteos', null],
            'jumping-jacks' => ['gemelos', 'hombros', 'jacks-sin-salto'],
            'salto-estrella' => ['cuadriceps', 'gluteos', 'jacks-sin-salto'],
            'escaladores' => ['cadera', 'core', 'marcha'],
            'rodillas-altas' => ['cadera', 'gemelos', 'marcha'],
            'marcha' => ['cadera', 'gemelos', null],
            'burpee' => ['cuadriceps', 'pecho', 'marcha'],
            'sentadilla-salto' => ['cuadriceps', 'gluteos', 'sentadilla'],
            'jacks-sin-salto' => ['gluteos', 'hombros', 'marcha'],
        ] as $slug => [$primary, $secondary, $alternative]) {
            $exercise = $exercises[$slug];
            foreach (['primary' => $primary, 'secondary' => $secondary] as $role => $muscle) {
                ExerciseMuscleGroup::firstOrCreate(['exercise_id' => $exercise->id, 'muscle_group_id' => $groups[$muscle]], ['role' => $role]);
            }
            if ($alternative && ! $exercise->lower_impact_id) {
                $exercise->lowerImpact()->associate($exercises[$alternative])->save();
            }
        }
    }
}
