<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_teacher', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->primary(['course_id', 'teacher_id']);
        });

        DB::table('courses')->select(['id', 'teacher_id'])->orderBy('id')->each(function ($course) {
            DB::table('course_teacher')->insertOrIgnore([
                'course_id' => $course->id,
                'teacher_id' => $course->teacher_id,
            ]);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn('teacher_id');
        });
        Schema::table('teachers', fn (Blueprint $table) => $table->dropColumn('specialty'));
    }

    public function down(): void
    {
        Schema::table('teachers', fn (Blueprint $table) => $table->string('specialty')->default('Sin cursos asignados'));
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->constrained()->restrictOnDelete();
        });

        DB::table('course_teacher')->orderBy('course_id')->get()->groupBy('course_id')->each(function ($relations, $courseId) {
            DB::table('courses')->where('id', $courseId)->update(['teacher_id' => $relations->first()->teacher_id]);
        });

        Schema::dropIfExists('course_teacher');
    }
};
