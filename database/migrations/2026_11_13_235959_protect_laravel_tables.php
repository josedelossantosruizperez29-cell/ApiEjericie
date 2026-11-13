<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = ['users', 'sessions', 'password_reset_tokens', 'personal_access_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            // The marker records whether this migration enabled RLS for a faithful rollback.
            DB::unprepared("
                DO \$\$ BEGIN
                    IF NOT (SELECT relrowsecurity FROM pg_class WHERE oid = 'public.$table'::regclass) THEN
                        ALTER TABLE public.$table ENABLE ROW LEVEL SECURITY;
                        CREATE POLICY fit_enabled_rls ON public.$table AS RESTRICTIVE USING (true);
                    END IF;
                    CREATE POLICY fit_backend_only ON public.$table AS RESTRICTIVE
                        USING (current_user NOT IN ('anon', 'authenticated'))
                        WITH CHECK (current_user NOT IN ('anon', 'authenticated'));
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared("
                DO \$\$ BEGIN
                    DROP POLICY fit_backend_only ON public.$table;
                    IF EXISTS (SELECT FROM pg_policy WHERE polrelid = 'public.$table'::regclass AND polname = 'fit_enabled_rls') THEN
                        DROP POLICY fit_enabled_rls ON public.$table;
                        ALTER TABLE public.$table DISABLE ROW LEVEL SECURITY;
                    END IF;
                END \$\$;
            ");
        }
    }
};
