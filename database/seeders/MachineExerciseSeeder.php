<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class MachineExerciseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'slug' => 'press-sentado',
                'name' => 'Press de pecho sentado',
                'category' => 'machine',
                'movement' => 'press',
                'difficulty' => 'beginner',
                'measurement' => 'reps',
                'description' => 'Empuje horizontal con respaldo',
                'instructions' => 'Sitúa las empuñaduras a la altura del pecho, empuja y regresa con control.',
                'safety_notes' => 'Mantén la espalda apoyada.',
                'active' => true,
                'penalty_eligible' => true,
            ],
            [
                'slug' => 'mariposa',
                'name' => 'Aperturas de pecho con mariposa',
                'category' => 'machine',
                'movement' => 'mariposa',
                'difficulty' => 'beginner',
                'measurement' => 'reps',
                'description' => 'Aducción horizontal en máquina',
                'instructions' => 'Apoya los brazos, acércalos delante del pecho y vuelve despacio.',
                'safety_notes' => 'Evita abrir los brazos más allá de un rango cómodo.',
                'active' => true,
                'penalty_eligible' => true,
            ],
            [
                'slug' => 'jalon',
                'name' => 'Jalón al pecho en polea alta',
                'category' => 'machine',
                'movement' => 'polea-alta',
                'difficulty' => 'beginner',
                'measurement' => 'reps',
                'description' => 'Tracción vertical sentada',
                'instructions' => 'Sujeta la barra, lleva los codos hacia abajo y devuelve la carga lentamente.',
                'safety_notes' => 'Lleva la barra delante de la cabeza.',
                'active' => true,
                'penalty_eligible' => true,
            ],
            [
                'slug' => 'remo',
                'name' => 'Remo sentado en polea baja',
                'category' => 'machine',
                'movement' => 'polea-baja',
                'difficulty' => 'beginner',
                'measurement' => 'reps',
                'description' => 'Tracción horizontal en polea',
                'instructions' => 'Siéntate frente a la polea y acerca el agarre al tronco manteniendo la espalda estable.',
                'safety_notes' => 'Evita balancear el tronco.',
                'active' => true,
                'penalty_eligible' => true,
            ],
            [
                'slug' => 'extension-piernas',
                'name' => 'Extensión de piernas',
                'category' => 'machine',
                'movement' => 'extension',
                'difficulty' => 'beginner',
                'measurement' => 'reps',
                'description' => 'Extensión de rodillas sentada',
                'instructions' => 'Alinea las rodillas con el eje, extiende las piernas y baja despacio.',
                'safety_notes' => 'Ajusta el rodillo y evita golpear el tope.',
                'active' => true,
                'penalty_eligible' => true,
            ],
            [
                'slug' => 'curl-predicador',
                'name' => 'Curl de bíceps con apoyo predicador',
                'category' => 'machine',
                'movement' => 'predicador',
                'difficulty' => 'beginner',
                'measurement' => 'reps',
                'description' => 'Flexión de codo con brazo apoyado',
                'instructions' => 'Apoya los brazos y flexiona los codos manteniendo los hombros estables.',
                'safety_notes' => 'Verifica el accesorio de carga antes de utilizarlo.',
                'active' => false,
                'penalty_eligible' => false,
            ],
        ] as $attributes) {
            Exercise::firstOrCreate(['slug' => $attributes['slug']], $attributes);
        }
    }
}
