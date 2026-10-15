<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->string('description', 250);
            $table->string('scope', 20);
            $table->string('icon', 60)->default('badge');
            $table->boolean('active')->default(true);
            $table->boolean('system')->default(false);
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['code' => 'admin', 'name' => 'Administrador', 'description' => 'Acceso completo a la configuración y gestión académica.', 'scope' => 'admin', 'icon' => 'admin_panel_settings', 'active' => true, 'system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'docente', 'name' => 'Docente', 'description' => 'Acceso a aulas, cursos, asistencia y calificaciones asignadas.', 'scope' => 'docente', 'icon' => 'school', 'active' => true, 'system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'padre', 'name' => 'Padre / Apoderado', 'description' => 'Consulta de información académica de sus estudiantes vinculados.', 'scope' => 'padre', 'icon' => 'family_restroom', 'active' => true, 'system' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role')->references('code')->on('roles')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role']);
        });
        Schema::dropIfExists('roles');
    }
};
