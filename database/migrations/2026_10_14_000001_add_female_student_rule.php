<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Los registros anteriores quedan pendientes de revisión; no se infiere el sexo por el nombre.
            $table->char('sex', 1)->nullable()->after('name');
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_female_only CHECK (sex IS NULL OR sex = 'F')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_female_only');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('sex');
        });
    }
};
