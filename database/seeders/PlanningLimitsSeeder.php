<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class PlanningLimitsSeeder extends Seeder
{
    public function run(): void
    {
        $levels = config('training.levels');
        Exercise::where('measurement', 'reps')->where('active', true)->whereRaw("rep_limits IN ('{}'::jsonb, '[]'::jsonb)")->update([
            'rep_limits' => json_encode(array_map(fn ($limits) => $limits['max_reps'], $levels), JSON_THROW_ON_ERROR),
        ]);
    }
}
