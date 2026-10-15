<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->string('period_type', 20)->default('Bimestral')->after('ends_on');
        });

        Schema::create('administrative_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('dni', 8)->unique();
            $table->string('email')->unique();
            $table->string('phone', 25)->nullable();
            $table->string('position', 80);
            $table->string('status', 20)->default('Activo');
            $table->timestamps();
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('grade')->nullable();
            $table->string('name', 250);
            $table->text('capacity');
            $table->text('performance');
            $table->text('evaluation_criterion');
            $table->string('status', 20)->default('Activo');
            $table->timestamps();
            $table->index(['course_id', 'grade']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('weekly_hours')->default(1);
            $table->string('status', 20)->default('Activo');
            $table->timestamps();
            $table->unique(['teacher_id', 'course_id', 'classroom_id', 'academic_period_id'], 'teacher_assignment_unique');
        });

        Schema::create('academic_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('type', 40);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Programado');
            $table->timestamps();
            $table->index(['academic_year_id', 'starts_at']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->string('type', 50);
            $table->date('evaluation_date');
            $table->decimal('max_score', 5, 2)->default(20);
            $table->decimal('weight', 5, 2)->default(100);
            $table->string('status', 20)->default('Programada');
            $table->timestamps();
            $table->index(['classroom_id', 'academic_period_id']);
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('achievement_level', 30)->nullable();
            $table->string('observation', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['evaluation_id', 'enrollment_id']);
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('document_number', 80)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 20)->default('Vigente');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('student_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->text('observation');
            $table->date('observed_on');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['student_observations', 'student_documents', 'evaluation_scores', 'evaluations', 'academic_events', 'teacher_assignments', 'competencies', 'administrative_staff'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('academic_years', fn (Blueprint $table) => $table->dropColumn('period_type'));
    }
};
