<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->text('image_url')->nullable();
        });
        DB::statement("ALTER TABLE exercises ADD CONSTRAINT exercises_public_image_url CHECK (image_url ~ '^https://[A-Za-z0-9.-]+(:[0-9]+)?/[^[:space:]?#]+$')");
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
