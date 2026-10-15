<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('dni', 8)->nullable()->unique();
            $table->string('phone', 25);
            $table->string('email')->nullable();
            $table->string('address', 250)->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('status')->default('Activo');
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('guardian_id')->nullable()->after('guardian_user_id')->constrained('guardians')->restrictOnDelete();
        });

        DB::table('students')->orderBy('id')->get()->each(function ($student) {
            $existingGuardian = DB::table('guardians');
            if ($student->guardian_user_id) {
                $existingGuardian->where('user_id', $student->guardian_user_id);
            } else {
                $existingGuardian
                    ->whereNull('user_id')
                    ->where('name', $student->guardian)
                    ->where('phone', $student->phone);
            }
            $guardianId = $existingGuardian->value('id');

            if (! $guardianId) {
                $guardianId = DB::table('guardians')->insertGetId([
                    'name' => $student->guardian,
                    'phone' => $student->phone,
                    'email' => $student->guardian_user_id ? DB::table('users')->where('id', $student->guardian_user_id)->value('email') : null,
                    'user_id' => $student->guardian_user_id,
                    'status' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('students')->where('id', $student->id)->update(['guardian_id' => $guardianId]);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guardian_id');
        });
        Schema::dropIfExists('guardians');
    }
};
