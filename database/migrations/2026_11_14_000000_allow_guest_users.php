<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('last_name')->nullable();
            $table->string('status')->default('active');
            $table->uuid('public_id')->default(DB::raw('gen_random_uuid()'))->unique();
        });
    }

    public function down(): void
    {
        // PostgreSQL rejects rollback atomically if guest credentials are still null.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->dropColumn(['public_id', 'last_name', 'status']);
        });
    }
};
