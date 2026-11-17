<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Additive: the modality is a preference, plus an immutable snapshot on each plan and generation. */
    public function up(): void
    {
        foreach (['user_preferences', 'ai_generations', 'training_plans'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('training_mode')->nullable());
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE $table ADD CHECK (training_mode IN ('machine','bodyweight'))");
            }
        }

        // Existing profiles: keep a modality only when the configured equipment makes it unambiguous.
        // Owning a multigym means machine training; owning no equipment at all means bodyweight.
        DB::table('user_preferences')->whereIn('user_id', function ($query) {
            $query->select('user_equipment.user_id')->from('user_equipment')
                ->join('equipment', 'equipment.id', '=', 'user_equipment.equipment_id')
                ->where('equipment.category', 'multigym');
        })->update(['training_mode' => 'machine']);
        DB::table('user_preferences')->whereNotIn('user_id', function ($query) {
            $query->select('user_id')->from('user_equipment');
        })->update(['training_mode' => 'bodyweight']);
    }

    public function down(): void
    {
        foreach (['user_preferences', 'ai_generations', 'training_plans'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE $table DROP CONSTRAINT IF EXISTS {$table}_training_mode_check");
            }
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('training_mode'));
        }
    }
};
