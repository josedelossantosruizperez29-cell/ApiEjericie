<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', fn (Blueprint $t) => $t->integer('completion_percent')->default(100));
        DB::statement('ALTER TABLE workout_sessions ADD CHECK (completion_percent BETWEEN 1 AND 100)');
        Schema::table('ai_generations', function (Blueprint $t) {
            $t->uuid('request_key')->nullable();
            $t->unique(['user_id', 'request_key']);
        });
        foreach (['kcal', 'protein_g', 'carbs_g', 'fat_g'] as $nutrient) {
            DB::statement("ALTER TABLE meal_entry_items ALTER COLUMN $nutrient DROP NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (['kcal', 'protein_g', 'carbs_g', 'fat_g'] as $nutrient) {
            DB::statement("ALTER TABLE meal_entry_items ALTER COLUMN $nutrient SET NOT NULL");
        }
        Schema::table('ai_generations', fn (Blueprint $t) => $t->dropColumn('request_key'));
        Schema::table('workout_sessions', fn (Blueprint $t) => $t->dropColumn('completion_percent'));
    }
};
