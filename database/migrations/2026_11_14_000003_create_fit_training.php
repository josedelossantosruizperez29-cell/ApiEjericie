<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plans', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignId('user_id')->constrained('users');
            $table->index('goal_id');
            $table->foreignUuid('goal_id')->nullable()->constrained('user_goals');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->integer('version')->default(1);
            $table->string('status')->default('draft');
            $table->string('source')->default('manual');
            $table->timestampsTz();
            $table->unique(['user_id', 'starts_on', 'version'], 'training_plans_u0');
            $table->unique(['id', 'user_id'], 'training_plans_u1');
            $table->foreign(['goal_id', 'user_id'], 'training_plans_owner_0')->references(['id', 'user_id'])->on('user_goals');
        });
        DB::statement("ALTER TABLE training_plans ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE training_plans ADD CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE training_plans ADD CHECK (version > 0)');

        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->index('training_plan_id');
            $table->foreignUuid('training_plan_id')->constrained('training_plans');
            $table->date('scheduled_date');
            $table->string('timezone');
            $table->timestampTz('cutoff_at');
            $table->string('kind');
            $table->string('status')->default('scheduled');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->index('rescheduled_from_id');
            $table->foreignUuid('rescheduled_from_id')->nullable()->constrained('workout_sessions');
            $table->timestampsTz();
            $table->unique(['id', 'user_id'], 'workout_sessions_u0');
            $table->index(['status', 'cutoff_at'], 'workout_sessions_i0');
            $table->foreign(['training_plan_id', 'user_id'], 'workout_sessions_owner_0')->references(['id', 'user_id'])->on('training_plans');
            $table->foreign(['rescheduled_from_id', 'user_id'], 'workout_sessions_owner_1')->references(['id', 'user_id'])->on('workout_sessions');
        });
        DB::statement("CREATE UNIQUE INDEX workout_sessions_one_day ON workout_sessions (user_id, scheduled_date) WHERE status NOT IN ('cancelled','rescheduled')");
        DB::statement("ALTER TABLE workout_sessions ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE workout_sessions ADD CHECK (status IN ('scheduled','in_progress','completed','missed','cancelled','rescheduled'))");
        DB::statement('ALTER TABLE workout_sessions ADD CHECK (finished_at >= started_at)');
        DB::statement("ALTER TABLE workout_sessions ADD CHECK (status <> 'completed' OR finished_at IS NOT NULL)");

        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignUuid('workout_session_id')->constrained('workout_sessions');
            $table->index('exercise_id');
            $table->foreignUuid('exercise_id')->constrained('exercises');
            $table->integer('position');
            $table->integer('sets');
            $table->integer('base_reps')->nullable();
            $table->integer('rest_seconds')->default(0);
            $table->integer('duration_seconds')->nullable();
            $table->decimal('distance_m', 12, 3)->nullable();
            $table->decimal('load_kg', 12, 3)->nullable();
            $table->jsonb('prescription');
            $table->timestampsTz();
            $table->unique(['workout_session_id', 'position'], 'workout_exercises_u0');
        });
        DB::statement("ALTER TABLE workout_exercises ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE workout_exercises ADD CHECK (position > 0)');
        DB::statement('ALTER TABLE workout_exercises ADD CHECK (sets > 0)');
        DB::statement('ALTER TABLE workout_exercises ADD CHECK (base_reps > 0)');
        DB::statement('ALTER TABLE workout_exercises ADD CHECK (rest_seconds >= 0)');
        DB::statement('ALTER TABLE workout_exercises ADD CHECK (duration_seconds >= 0)');
        DB::statement('ALTER TABLE workout_exercises ADD CHECK (distance_m >= 0)');
        DB::statement('ALTER TABLE workout_exercises ADD CHECK (load_kg >= 0)');

        Schema::create('exercise_set_logs', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignUuid('workout_exercise_id')->constrained('workout_exercises');
            $table->integer('set_number');
            $table->integer('reps')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->decimal('distance_m', 12, 3)->nullable();
            $table->decimal('load_kg', 12, 3)->nullable();
            $table->integer('perceived_effort')->nullable();
            $table->timestampTz('logged_at');
            $table->timestampsTz();
            $table->unique(['workout_exercise_id', 'set_number'], 'exercise_set_logs_u0');
        });
        DB::statement("ALTER TABLE exercise_set_logs ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE exercise_set_logs ADD CHECK (perceived_effort BETWEEN 1 AND 10)');
        DB::statement('ALTER TABLE exercise_set_logs ADD CHECK (set_number > 0)');
        DB::statement('ALTER TABLE exercise_set_logs ADD CHECK (reps >= 0)');
        DB::statement('ALTER TABLE exercise_set_logs ADD CHECK (duration_seconds >= 0)');
        DB::statement('ALTER TABLE exercise_set_logs ADD CHECK (distance_m >= 0)');
        DB::statement('ALTER TABLE exercise_set_logs ADD CHECK (load_kg >= 0)');
        DB::statement('ALTER TABLE exercise_set_logs ADD CHECK (perceived_effort >= 0)');

        Schema::create('training_plan_revisions', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignUuid('training_plan_id')->constrained('training_plans');
            $table->integer('version');
            $table->string('source');
            $table->text('justification');
            $table->jsonb('changes');
            $table->timestampsTz();
            $table->unique(['training_plan_id', 'version'], 'training_plan_revisions_u0');
        });
        DB::statement("ALTER TABLE training_plan_revisions ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE training_plan_revisions ADD CHECK (version > 0)');

        Schema::create('penalty_rules', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->string('slug')->unique();
            $table->integer('increment_reps');
            $table->integer('max_extra_reps');
            $table->boolean('active')->default(true);
            $table->boolean('adjustments_enabled')->default(false);
            $table->jsonb('conditions')->default('{}');
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE penalty_rules ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE penalty_rules ADD CHECK (increment_reps >= 0)');
        DB::statement('ALTER TABLE penalty_rules ADD CHECK (max_extra_reps >= 0)');

        Schema::create('penalties', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->unique('origin_session_id');
            $table->foreignUuid('origin_session_id')->constrained('workout_sessions');
            $table->date('missed_on');
            $table->string('status')->default('pending');
            $table->timestampTz('activated_at');
            $table->unique('resolved_session_id');
            $table->foreignUuid('resolved_session_id')->nullable()->constrained('workout_sessions');
            $table->timestampTz('resolved_at')->nullable();
            $table->index('rule_id');
            $table->foreignUuid('rule_id')->constrained('penalty_rules');
            $table->timestampsTz();
            $table->index(['user_id', 'status', 'missed_on'], 'penalties_i0');
            $table->foreign(['origin_session_id', 'user_id'], 'penalties_owner_0')->references(['id', 'user_id'])->on('workout_sessions');
            $table->foreign(['resolved_session_id', 'user_id'], 'penalties_owner_1')->references(['id', 'user_id'])->on('workout_sessions');
        });
        DB::statement("ALTER TABLE penalties ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE penalties ADD CHECK ((status = 'pending' AND resolved_at IS NULL AND resolved_session_id IS NULL) OR (status = 'resolved' AND resolved_at IS NOT NULL AND resolved_session_id IS NOT NULL AND origin_session_id <> resolved_session_id))");

        Schema::create('workout_penalty_applications', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->unique('workout_exercise_id');
            $table->foreignUuid('workout_exercise_id')->constrained('workout_exercises');
            $table->index('rule_id');
            $table->foreignUuid('rule_id')->constrained('penalty_rules');
            $table->integer('pending_count');
            $table->jsonb('original');
            $table->jsonb('applied');
            $table->string('reason');
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE workout_penalty_applications ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE workout_penalty_applications ADD CHECK (pending_count >= 0)');

    }

    public function down(): void
    {
        Schema::drop('workout_penalty_applications');
        Schema::drop('penalties');
        Schema::drop('penalty_rules');
        Schema::drop('training_plan_revisions');
        Schema::drop('exercise_set_logs');
        Schema::drop('workout_exercises');
        Schema::drop('workout_sessions');
        Schema::drop('training_plans');
    }
};
