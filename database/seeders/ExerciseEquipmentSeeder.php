<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\ExerciseEquipment;
use Illuminate\Database\Seeder;

class ExerciseEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $machine = Equipment::where('slug', 'evo-500')->sole();
        foreach ([
            'press-sentado' => 'press',
            'mariposa' => 'mariposa',
            'jalon' => 'polea-alta',
            'remo' => 'polea-baja',
            'extension-piernas' => 'extension',
            'curl-predicador' => 'predicador',
        ] as $slug => $capability) {
            ExerciseEquipment::firstOrCreate([
                'exercise_id' => Exercise::where('slug', $slug)->sole()->id,
                'equipment_id' => $machine->id,
            ], [
                'capability_id' => $machine->equipmentCapabilities()->where('slug', $capability)->sole()->id,
                'accessory' => $slug === 'curl-predicador' ? 'Accesorio de carga pendiente de confirmar' : null,
            ]);
        }
    }
}
