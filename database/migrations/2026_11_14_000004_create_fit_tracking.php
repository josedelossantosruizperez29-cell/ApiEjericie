<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_items', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('reference_unit');
            $table->decimal('reference_quantity', 12, 3);
            $table->decimal('kcal', 12, 3);
            $table->decimal('protein_g', 12, 3);
            $table->decimal('carbs_g', 12, 3);
            $table->decimal('fat_g', 12, 3);
            $table->text('source');
            $table->string('verification');
            $table->date('source_date');
            $table->decimal('confidence', 12, 3)->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE food_items ADD CHECK (reference_quantity > 0)');
        DB::statement('ALTER TABLE food_items ADD CHECK (kcal >= 0)');
        DB::statement('ALTER TABLE food_items ADD CHECK (protein_g >= 0)');
        DB::statement('ALTER TABLE food_items ADD CHECK (carbs_g >= 0)');
        DB::statement('ALTER TABLE food_items ADD CHECK (fat_g >= 0)');
        DB::statement('ALTER TABLE food_items ADD CHECK (confidence BETWEEN 0 AND 1)');

        Schema::create('meal_entries', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('kind');
            $table->timestampTz('eaten_at');
            $table->date('local_date');
            $table->string('timezone');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'local_date'], 'meal_entries_i0');
        });
        DB::statement("ALTER TABLE meal_entries ADD CHECK (kind IN ('breakfast','lunch','dinner','snack'))");

        Schema::create('meal_entry_items', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('meal_entry_id');
            $table->foreignUuid('meal_entry_id')->constrained('meal_entries');
            $table->index('food_item_id');
            $table->foreignUuid('food_item_id')->nullable()->constrained('food_items');
            $table->string('name');
            $table->decimal('quantity', 12, 3);
            $table->string('unit');
            $table->decimal('grams', 12, 3)->nullable();
            $table->decimal('kcal', 12, 3);
            $table->decimal('protein_g', 12, 3);
            $table->decimal('carbs_g', 12, 3);
            $table->decimal('fat_g', 12, 3);
            $table->text('source');
            $table->timestampTz('estimated_at');
            $table->decimal('confidence', 12, 3)->nullable();
            $table->unique('supersedes_id');
            $table->foreignUuid('supersedes_id')->nullable()->constrained('meal_entry_items');
            $table->timestampsTz();
            $table->unique(['id', 'meal_entry_id'], 'meal_entry_items_u0');
            $table->foreign(['supersedes_id', 'meal_entry_id'], 'meal_entry_items_owner_0')->references(['id', 'meal_entry_id'])->on('meal_entry_items');
        });
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (supersedes_id <> id)');
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (quantity > 0)');
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (grams >= 0)');
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (kcal >= 0)');
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (protein_g >= 0)');
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (carbs_g >= 0)');
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (fat_g >= 0)');
        DB::statement('ALTER TABLE meal_entry_items ADD CHECK (confidence BETWEEN 0 AND 1)');

        Schema::create('nutrition_goals', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('kcal', 12, 3)->nullable();
            $table->decimal('protein_g', 12, 3)->nullable();
            $table->decimal('carbs_g', 12, 3)->nullable();
            $table->decimal('fat_g', 12, 3)->nullable();
            $table->text('source');
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE nutrition_goals ADD CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE nutrition_goals ADD CHECK (kcal >= 0)');
        DB::statement('ALTER TABLE nutrition_goals ADD CHECK (protein_g >= 0)');
        DB::statement('ALTER TABLE nutrition_goals ADD CHECK (carbs_g >= 0)');
        DB::statement('ALTER TABLE nutrition_goals ADD CHECK (fat_g >= 0)');

        Schema::create('nutrition_assessments', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->timestampTz('assessed_at');
            $table->text('observations');
            $table->jsonb('recommendations')->default('{}');
            $table->decimal('confidence', 12, 3)->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE nutrition_assessments ADD CHECK (confidence BETWEEN 0 AND 1)');

        Schema::create('weight_logs', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignId('user_id')->constrained('users');
            $table->timestampTz('measured_at');
            $table->decimal('weight_kg', 12, 3);
            $table->string('source');
            $table->timestampsTz();
            $table->unique(['user_id', 'measured_at'], 'weight_logs_u0');
        });
        DB::statement('ALTER TABLE weight_logs ADD CHECK (weight_kg > 0)');

        Schema::create('body_measurements', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->timestampTz('measured_at');
            $table->string('site');
            $table->decimal('value', 12, 3);
            $table->string('unit');
            $table->string('source');
            $table->timestampsTz();
            $table->index(['user_id', 'measured_at'], 'body_measurements_i0');
        });
        DB::statement('ALTER TABLE body_measurements ADD CHECK (value > 0)');

        Schema::create('streak_events', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignId('user_id')->constrained('users');
            $table->index('workout_session_id');
            $table->foreignUuid('workout_session_id')->nullable()->constrained('workout_sessions');
            $table->string('event_key');
            $table->string('kind');
            $table->date('local_date');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->unique(['user_id', 'event_key'], 'streak_events_u0');
            $table->foreign(['workout_session_id', 'user_id'], 'streak_events_owner_0')->references(['id', 'user_id'])->on('workout_sessions');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('kind');
            $table->time('local_time');
            $table->string('timezone');
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();
            $table->unique(['user_id', 'kind'], 'notification_preferences_u0');
        });

        Schema::create('notification_events', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('event_key');
            $table->string('kind');
            $table->string('channel')->default('local');
            $table->timestampTz('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->integer('attempts')->default(0);
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'event_key'], 'notification_events_u0');
        });
        DB::statement('ALTER TABLE notification_events ADD CHECK (attempts >= 0)');

        Schema::create('ai_generations', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('kind');
            $table->string('model');
            $table->string('prompt_version');
            $table->string('schema_version');
            $table->string('status');
            $table->timestampTz('requested_at');
            $table->jsonb('response')->default('{}');
            $table->jsonb('usage')->default('{}');
            $table->string('error_code')->nullable();
            $table->timestampsTz();
            $table->unique(['id', 'user_id'], 'ai_generations_u0');
        });

        Schema::create('ai_plan_recommendations', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->index('user_id');
            $table->foreignId('user_id')->constrained('users');
            $table->index('generation_id');
            $table->foreignUuid('generation_id')->constrained('ai_generations');
            $table->index('training_plan_id');
            $table->foreignUuid('training_plan_id')->nullable()->constrained('training_plans');
            $table->jsonb('recommendation');
            $table->string('validation_status');
            $table->text('reason')->nullable();
            $table->timestampsTz();
            $table->foreign(['generation_id', 'user_id'], 'ai_plan_recommendations_owner_0')->references(['id', 'user_id'])->on('ai_generations');
            $table->foreign(['training_plan_id', 'user_id'], 'ai_plan_recommendations_owner_1')->references(['id', 'user_id'])->on('training_plans');
        });

    }

    public function down(): void
    {
        Schema::drop('ai_plan_recommendations');
        Schema::drop('ai_generations');
        Schema::drop('notification_events');
        Schema::drop('notification_preferences');
        Schema::drop('streak_events');
        Schema::drop('body_measurements');
        Schema::drop('weight_logs');
        Schema::drop('nutrition_assessments');
        Schema::drop('nutrition_goals');
        Schema::drop('meal_entry_items');
        Schema::drop('meal_entries');
        Schema::drop('food_items');
    }
};
