<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\EquipmentCapability;
use Illuminate\Database\Seeder;

class EquipmentCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $machine = Equipment::where('slug', 'evo-500')->sole();
        foreach ([
            'press' => 'Brazos de press',
            'mariposa' => 'Brazos de mariposa',
            'polea-alta' => 'Polea alta',
            'polea-baja' => 'Polea baja',
            'extension' => 'Estación de extensión de piernas',
            'predicador' => 'Apoyo predicador',
        ] as $slug => $name) {
            EquipmentCapability::firstOrCreate(['equipment_id' => $machine->id, 'slug' => $slug], ['name' => $name]);
        }
    }
}
