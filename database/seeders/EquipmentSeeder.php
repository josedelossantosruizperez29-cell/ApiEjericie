<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        Equipment::firstOrCreate(['slug' => 'evo-500'], [
            'name' => 'Multigimnasio EVO 500', 'brand' => 'Evolution Fitness', 'model' => 'EVO 500',
            'category' => 'multigym', 'description' => 'Multigimnasio doméstico con torre de pesas.',
        ]);
    }
}
