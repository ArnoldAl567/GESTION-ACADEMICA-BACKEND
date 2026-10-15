<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX academic_years_single_active_unique ON academic_years (status) WHERE status = 'Activo'");
        DB::statement("CREATE UNIQUE INDEX academic_periods_single_active_unique ON academic_periods (status) WHERE status = 'Activo'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS academic_periods_single_active_unique');
        DB::statement('DROP INDEX IF EXISTS academic_years_single_active_unique');
    }
};
