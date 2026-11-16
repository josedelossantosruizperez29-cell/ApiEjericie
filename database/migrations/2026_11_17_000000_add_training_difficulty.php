<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['user_preferences', 'ai_generations', 'training_plans'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('training_difficulty')->default('intermediate'));
            DB::statement("ALTER TABLE $table ADD CHECK (training_difficulty IN ('easy','intermediate','hard'))");
        }
    }

    public function down(): void
    {
        foreach (['user_preferences', 'ai_generations', 'training_plans'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('training_difficulty'));
        }
    }
};
