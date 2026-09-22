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
            $table->string('role')->default('padre')->index();
            $table->boolean('active')->default(true);
        });
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('grade');
            $table->string('section', 3);
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->timestamps();
            $table->unique(['year', 'grade', 'section']);
        });
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('dni', 8)->unique();
            $table->string('email')->unique();
            $table->string('phone', 25)->nullable();
            $table->string('specialty');
            $table->string('status')->default('Activo');
            $table->string('color', 7)->default('#3b82f6');
            $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('dni', 8)->unique();
            $table->date('birth_date')->nullable();
            $table->string('guardian');
            $table->string('phone', 25);
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('Activo');
            $table->string('color', 7)->default('#3b82f6');
            $table->timestamps();
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category');
            $table->text('description')->nullable();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('hours')->default(4);
            $table->string('color', 7)->default('#3b82f6');
            $table->string('soft', 7)->default('#eff6ff');
            $table->timestamps();
        });
        Schema::create('classroom_course', function (Blueprint $table) {
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->primary(['classroom_id', 'course_id']);
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->date('enrolled_at');
            $table->string('status')->default('Activo');
            $table->timestamps();
            $table->unique(['student_id', 'year']);
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 1);
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['enrollment_id', 'date']);
        });
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('period');
            $table->decimal('score', 4, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['enrollment_id', 'course_id', 'period']);
        });
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('day');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->index(['classroom_id', 'day']);
            $table->index(['teacher_id', 'day']);
        });
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('subject');
            $table->text('body');
            $table->string('audience')->default('all');
            $table->foreignId('classroom_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->boolean('draft')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('communication_user', function (Blueprint $table) {
            $table->foreignId('communication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->boolean('archived')->default(false);
            $table->primary(['communication_id', 'user_id']);
        });
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('period')->default(1);
            $table->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_score_range CHECK (score >= 0 AND score <= 20 AND period BETWEEN 1 AND 4)');
            DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_status_valid CHECK (status IN ('P','F','T','J'))");
            DB::statement('ALTER TABLE classrooms ADD CONSTRAINT classrooms_grade_capacity CHECK (grade BETWEEN 1 AND 6 AND capacity > 0)');
            DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_valid_time CHECK (day BETWEEN 1 AND 5 AND starts_at < ends_at)');
        }
    }

    public function down(): void
    {
        foreach (['school_settings', 'communication_user', 'communications', 'schedules', 'grades', 'attendances', 'enrollments', 'classroom_course', 'courses', 'students', 'teachers', 'classrooms'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'active']));
    }
};
