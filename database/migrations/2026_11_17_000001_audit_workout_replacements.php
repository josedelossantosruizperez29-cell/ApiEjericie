<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', fn (Blueprint $t) => $t->smallInteger('replacements_used')->default(0));
        DB::statement('ALTER TABLE workout_sessions ADD CHECK (replacements_used BETWEEN 0 AND 3)');
        Schema::create('workout_replacements', function (Blueprint $t) {
            $t->foreignUuid('session_id')->constrained('workout_sessions');
            $t->uuid('operation_id');
            $t->primary(['session_id', 'operation_id']);
            $t->foreignUuid('assignment_id')->constrained('workout_exercises');
            $t->foreignUuid('previous_id')->constrained('exercises');
            $t->foreignUuid('new_id')->constrained('exercises');
            $t->jsonb('result');
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE workout_replacements ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        Schema::drop('workout_replacements');
        Schema::table('workout_sessions', fn (Blueprint $t) => $t->dropColumn('replacements_used'));
    }
};
