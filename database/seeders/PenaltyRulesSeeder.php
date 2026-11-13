<?php

namespace Database\Seeders;

use App\Models\PenaltyRule;
use Illuminate\Database\Seeder;

class PenaltyRulesSeeder extends Seeder
{
    public function run(): void
    {
        PenaltyRule::firstOrCreate(['slug' => 'daily-missed-v1'], [
            'increment_reps' => 5, 'max_extra_reps' => 0, 'adjustments_enabled' => false,
            'conditions' => ['resolution' => 'fifo', 'unit' => 'scheduled_day'],
        ]);
    }
}
