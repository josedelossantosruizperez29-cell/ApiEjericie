<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('category');
            $table->text('description');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE equipment ENABLE ROW LEVEL SECURITY');

        Schema::create('equipment_capabilities', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignUuid('equipment_id')->constrained('equipment');
            $table->string('slug');
            $table->string('name');
            $table->boolean('verified')->default(true);
            $table->timestampsTz();
            $table->unique(['equipment_id', 'slug'], 'equipment_capabilities_u0');
            $table->unique(['id', 'equipment_id'], 'equipment_capabilities_u1');
        });
        DB::statement('ALTER TABLE equipment_capabilities ENABLE ROW LEVEL SECURITY');

        Schema::create('user_equipment', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignId('user_id')->constrained('users');
            $table->index('equipment_id');
            $table->foreignUuid('equipment_id')->constrained('equipment');
            $table->timestampsTz();
            $table->unique(['user_id', 'equipment_id'], 'user_equipment_u0');
        });
        DB::statement('ALTER TABLE user_equipment ENABLE ROW LEVEL SECURITY');

        Schema::create('muscle_groups', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->index('parent_id');
            $table->foreignUuid('parent_id')->nullable()->constrained('muscle_groups');
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE muscle_groups ENABLE ROW LEVEL SECURITY');

        Schema::create('exercises', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->text('instructions');
            $table->string('category');
            $table->string('movement');
            $table->string('difficulty');
            $table->string('measurement');
            $table->text('safety_notes');
            $table->boolean('active')->default(true);
            $table->string('media_url')->nullable();
            $table->boolean('penalty_eligible')->default(false);
            $table->jsonb('rep_limits')->default('{}');
            $table->index('lower_impact_id');
            $table->foreignUuid('lower_impact_id')->nullable()->constrained('exercises');
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE exercises ENABLE ROW LEVEL SECURITY');
        DB::statement("ALTER TABLE exercises ADD CHECK (measurement IN ('reps','time','distance'))");
        DB::statement("ALTER TABLE exercises ADD CHECK (difficulty IN ('beginner','intermediate','advanced'))");
        DB::statement("ALTER TABLE exercises ADD CHECK (NOT penalty_eligible OR measurement = 'reps')");

        Schema::create('exercise_muscle_groups', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignUuid('exercise_id')->constrained('exercises');
            $table->index('muscle_group_id');
            $table->foreignUuid('muscle_group_id')->constrained('muscle_groups');
            $table->string('role');
            $table->timestampsTz();
            $table->unique(['exercise_id', 'muscle_group_id'], 'exercise_muscle_groups_u0');
        });
        DB::statement('ALTER TABLE exercise_muscle_groups ENABLE ROW LEVEL SECURITY');
        DB::statement("ALTER TABLE exercise_muscle_groups ADD CHECK (role IN ('primary','secondary'))");

        Schema::create('exercise_equipment', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->foreignUuid('exercise_id')->constrained('exercises');
            $table->index('equipment_id');
            $table->foreignUuid('equipment_id')->constrained('equipment');
            $table->index('capability_id');
            $table->foreignUuid('capability_id')->nullable()->constrained('equipment_capabilities');
            $table->string('accessory')->nullable();
            $table->timestampsTz();
            $table->unique(['exercise_id', 'equipment_id'], 'exercise_equipment_u0');
            $table->foreign(['capability_id', 'equipment_id'], 'exercise_equipment_owner_0')->references(['id', 'equipment_id'])->on('equipment_capabilities');
        });
        DB::statement('ALTER TABLE exercise_equipment ENABLE ROW LEVEL SECURITY');

    }

    public function down(): void
    {
        Schema::drop('exercise_equipment');
        Schema::drop('exercise_muscle_groups');
        Schema::drop('exercises');
        Schema::drop('muscle_groups');
        Schema::drop('user_equipment');
        Schema::drop('equipment_capabilities');
        Schema::drop('equipment');
    }
};
