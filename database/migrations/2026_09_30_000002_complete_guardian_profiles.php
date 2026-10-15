<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $profiles = [
            ['Carmen Espinoza Salazar', '46000001', 'carmen.espinoza@familias.colegio.edu.pe'],
            ['José Fernández Salazar', '46000002', 'jose.fernandez@familias.colegio.edu.pe'],
            ['Patricia Vega Ramos', '46000003', 'patricia.vega@familias.colegio.edu.pe'],
            ['Miguel Torres Huamán', '46000004', 'miguel.torres@familias.colegio.edu.pe'],
            ['Verónica Chávez Rojas', '46000005', 'veronica.chavez@familias.colegio.edu.pe'],
            ['Raúl Huanca Mamani', '46000006', 'raul.huanca@familias.colegio.edu.pe'],
            ['Elena Flores Quispe', '46000007', 'elena.flores@familias.colegio.edu.pe'],
            ['Jorge Castillo Méndez', '46000008', 'jorge.castillo@familias.colegio.edu.pe'],
            ['Maribel Condori Apaza', '46000009', 'maribel.condori@familias.colegio.edu.pe'],
            ['Luis Palomino García', '46000010', 'luis.palomino@familias.colegio.edu.pe'],
            ['Rosa Ruiz Mendoza', '46000011', 'rosa.ruiz@familias.colegio.edu.pe'],
            ['Fernando Vargas Soto', '46000012', 'fernando.vargas@familias.colegio.edu.pe'],
            ['Ana Díaz Romero', '46000013', 'ana.diaz@familias.colegio.edu.pe'],
            ['Carlos Flores Pérez', '46000014', 'carlos.flores@familias.colegio.edu.pe'],
            ['Julia León Castro', '46000015', 'julia.leon@familias.colegio.edu.pe'],
            ['Roberto Castro Peña', '46000016', 'roberto.castro@familias.colegio.edu.pe'],
            ['Claudia Vega Salas', '46000017', 'claudia.vega@familias.colegio.edu.pe'],
            ['Martín Rojas Campos', '46000018', 'martin.rojas@familias.colegio.edu.pe'],
            ['Teresa Silva Ponce', '46000019', 'teresa.silva@familias.colegio.edu.pe'],
            ['Héctor Ortiz Luna', '46000020', 'hector.ortiz@familias.colegio.edu.pe'],
            ['Mariana Quispe Valdez', '46000021', 'mariana.quispe@familias.colegio.edu.pe'],
        ];

        DB::transaction(function () use ($profiles) {
            DB::table('guardians')->orderBy('id')->get()->each(function ($guardian, int $index) use ($profiles) {
                [$name, $dni, $email] = $profiles[$index] ?? [
                    'Apoderado registrado '.($index + 1),
                    (string) (46000001 + $index),
                    'apoderado'.($index + 1).'@familias.colegio.edu.pe',
                ];

                $userId = $guardian->user_id;
                if ($userId) {
                    DB::table('users')->where('id', $userId)->update([
                        'name' => $name,
                        'email' => $email,
                        'role' => 'padre',
                        'active' => true,
                        'updated_at' => now(),
                    ]);
                } else {
                    $userId = DB::table('users')->insertGetId([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make('Familia2026!'),
                        'role' => 'padre',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('guardians')->where('id', $guardian->id)->update([
                    'name' => $name,
                    'dni' => $dni,
                    'email' => $email,
                    'user_id' => $userId,
                    'updated_at' => now(),
                ]);
                DB::table('students')->where('guardian_id', $guardian->id)->update([
                    'guardian' => $name,
                    'guardian_user_id' => $userId,
                    'updated_at' => now(),
                ]);
            });

            DB::statement('ALTER TABLE guardians ALTER COLUMN dni SET NOT NULL');
            DB::statement('ALTER TABLE guardians ALTER COLUMN user_id SET NOT NULL');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE guardians ALTER COLUMN dni DROP NOT NULL');
        DB::statement('ALTER TABLE guardians ALTER COLUMN user_id DROP NOT NULL');
    }
};
