<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->call([
            EquipmentSeeder::class, EquipmentCapabilitiesSeeder::class, MuscleGroupSeeder::class,
            MachineExerciseSeeder::class, BodyweightExerciseSeeder::class, ExerciseMuscleGroupSeeder::class,
            ExerciseEquipmentSeeder::class, PlanningLimitsSeeder::class, PenaltyRulesSeeder::class,
        ]));
    }
}
