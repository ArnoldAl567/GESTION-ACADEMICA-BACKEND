<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_settings')
            ->where('id', 1)
            ->update([
                'name' => 'Institución Educativa N° 80404',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('school_settings')
            ->where('id', 1)
            ->update([
                'name' => 'I.E. San Martín de Porres',
                'updated_at' => now(),
            ]);
    }
};
