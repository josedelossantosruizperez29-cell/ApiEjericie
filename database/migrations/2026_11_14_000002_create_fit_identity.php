<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->char('token_hash', 64)->unique();
            $table->string('device_label')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE guest_access_tokens ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE guest_access_tokens ADD CHECK (token_hash ~ '^[0-9a-f]{64}$')");

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->unique('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->date('birth_date')->nullable();
            $table->integer('age_years')->nullable();
            $table->date('age_as_of')->nullable();
            $table->decimal('weight_kg', 12, 3)->nullable();
            $table->decimal('height_cm', 12, 3)->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('level')->default('beginner');
            $table->string('activity_level')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE user_profiles ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE user_profiles ADD CHECK ((age_years IS NULL) = (age_as_of IS NULL))');
        DB::statement("ALTER TABLE user_profiles ADD CHECK (level IN ('beginner','intermediate','advanced'))");
        DB::statement('ALTER TABLE user_profiles ADD CHECK (age_years > 0)');
        DB::statement('ALTER TABLE user_profiles ADD CHECK (weight_kg > 0)');
        DB::statement('ALTER TABLE user_profiles ADD CHECK (height_cm > 0)');

        Schema::create('user_goals', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('kind');
            $table->date('start_date');
            $table->string('status')->default('active');
            $table->integer('priority')->default(1);
            $table->decimal('target_value', 12, 3)->nullable();
            $table->string('target_unit')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'user_id'], 'user_goals_u0');
        });
        DB::statement("ALTER TABLE user_goals ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE user_goals ADD CHECK (kind IN ('fat_loss','muscle_gain','strength_cardio'))");
        DB::statement('ALTER TABLE user_goals ADD CHECK (priority >= 0)');
        DB::statement('ALTER TABLE user_goals ADD CHECK (target_value >= 0)');

        Schema::create('user_onboarding', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->unique('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->integer('step')->default(0);
            $table->jsonb('answers')->default('{}');
            $table->jsonb('weekly_availability')->default('{}');
            $table->integer('session_minutes')->nullable();
            $table->time('preferred_time')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE user_onboarding ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE user_onboarding ADD CHECK (step >= 0)');
        DB::statement('ALTER TABLE user_onboarding ADD CHECK (session_minutes > 0)');

        Schema::create('user_health_constraints', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->text('description');
            $table->jsonb('avoided_movements')->default('{}');
            $table->index('exercise_id');
            $table->foreignUuid('exercise_id')->nullable()->constrained('exercises');
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE user_health_constraints ENABLE ROW LEVEL SECURITY");

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->unique('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('units')->default('metric');
            $table->boolean('penalty_adjustments')->default(false);
            $table->boolean('recovery')->default(false);
            $table->integer('max_extra_reps')->default(0);
            $table->time('daily_cutoff')->default('23:59');
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE user_preferences ENABLE ROW LEVEL SECURITY");
        DB::statement('ALTER TABLE user_preferences ADD CHECK (max_extra_reps >= 0)');

    }

    public function down(): void
    {
        Schema::drop('user_preferences');
        Schema::drop('user_health_constraints');
        Schema::drop('user_onboarding');
        Schema::drop('user_goals');
        Schema::drop('user_profiles');
        Schema::drop('guest_access_tokens');
    }
};
