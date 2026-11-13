<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION public.fit_preserve_meal_snapshot() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Insert a new meal_entry_items row referencing supersedes_id to correct this snapshot';
            END;
            $$;
            CREATE TRIGGER preserve_meal_snapshot BEFORE UPDATE OR DELETE ON public.meal_entry_items
                FOR EACH ROW EXECUTE FUNCTION public.fit_preserve_meal_snapshot();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER preserve_meal_snapshot ON public.meal_entry_items');
        DB::statement('DROP FUNCTION public.fit_preserve_meal_snapshot()');
    }
};
