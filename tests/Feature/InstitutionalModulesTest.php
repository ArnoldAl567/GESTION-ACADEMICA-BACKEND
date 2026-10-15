<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\CompetenciasPrimariaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
    }

    public function test_missing_academic_modules_have_persistent_crud_and_reporting(): void
    {
        $year = AcademicYear::where('status', 'Activo')->firstOrFail();
        $period = AcademicPeriod::where('academic_year_id', $year->id)->where('status', 'Activo')->firstOrFail();
        $teacher = Teacher::firstOrFail();
        $course = Course::firstOrFail();
        $enrollment = Enrollment::where('year', $year->year)->where('status', 'Activo')->firstOrFail();
        $classroom = Classroom::findOrFail($enrollment->classroom_id);
        $student = Student::findOrFail($enrollment->student_id);
        $course->classrooms()->syncWithoutDetaching([$classroom->id]);

        $this->postJson('/api/v1/management/administrative-staff', ['name' => 'María Directora', 'dni' => '71000001', 'email' => 'direccion@example.test', 'phone' => '999111222', 'position' => 'Director', 'status' => 'Activo'])->assertCreated();
        $competencyId = $this->postJson('/api/v1/management/competencies', ['course_id' => $course->id, 'grade' => $classroom->grade, 'name' => 'Resuelve problemas', 'capacity' => 'Modela situaciones', 'performance' => 'Explica el procedimiento', 'evaluation_criterion' => 'Resuelve correctamente', 'status' => 'Activo'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('teacher_assignments', ['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'academic_period_id' => $period->id, 'course_id' => $course->id, 'status' => 'Activo']);
        $this->postJson('/api/v1/management/academic-events', ['academic_year_id' => $year->id, 'classroom_id' => null, 'title' => 'Reunión general', 'type' => 'Reunión', 'starts_at' => $year->starts_on->format('Y-m-d').' 08:00:00', 'ends_at' => null, 'description' => 'Reunión institucional', 'status' => 'Programado'])->assertCreated();
        $this->postJson('/api/v1/management/student-documents', ['student_id' => $student->id, 'type' => 'Partida de nacimiento', 'document_number' => 'PN-001', 'issued_on' => null, 'expires_on' => null, 'status' => 'Vigente', 'notes' => null])->assertCreated();

        $evaluation = ['academic_period_id' => $period->id, 'classroom_id' => $classroom->id, 'course_id' => $course->id, 'competency_id' => $competencyId, 'type' => 'Práctica', 'evaluation_date' => $period->starts_on->format('Y-m-d'), 'max_score' => 20, 'status' => 'Aplicada'];
        $evaluationId = $this->postJson('/api/v1/management/evaluations', [...$evaluation, 'title' => 'Práctica calificada', 'weight' => 25])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/evaluations/'.$evaluationId.'/scores', ['records' => [['enrollment_id' => $enrollment->id, 'score' => 18, 'achievement_level' => 'AD', 'observation' => 'Buen desempeño']]])->assertOk();
        $secondEvaluationId = $this->postJson('/api/v1/management/evaluations', [...$evaluation, 'title' => 'Proyecto final', 'weight' => 75])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/evaluations/'.$secondEvaluationId.'/scores', ['records' => [['enrollment_id' => $enrollment->id, 'score' => 10]]])->assertOk();
        $this->postJson('/api/v1/management/evaluations', [...$evaluation, 'title' => 'Peso excedido', 'weight' => 1])->assertUnprocessable()->assertJsonValidationErrors('weight');
        $this->postJson('/api/v1/management/student-observations', ['enrollment_id' => $enrollment->id, 'academic_period_id' => $period->id, 'type' => 'Reconocimiento', 'observation' => 'Participa activamente.', 'observed_on' => $period->starts_on->format('Y-m-d')])->assertCreated();

        $this->getJson('/api/v1/report-cards?classroom_id='.$classroom->id.'&academic_period_id='.$period->id)
            ->assertOk()
            ->assertJsonPath('data.0.student.id', $student->id)
            ->assertJsonPath('data.0.average', 12)
            ->assertJsonPath('data.0.achievement_level', 'B')
            ->assertJsonPath('data.0.course_averages.0.weight_total', 100);
        $this->get('/api/v1/report-cards/'.$enrollment->id.'/pdf?academic_period_id='.$period->id)
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        foreach (['administrative-staff', 'competencies', 'teacher-assignments', 'academic-events', 'evaluations', 'student-documents', 'student-observations'] as $resource) {
            $this->getJson('/api/v1/management/'.$resource.'?per_page=100')->assertOk()->assertJsonCount($resource === 'evaluations' ? 2 : ($resource === 'teacher-assignments' ? 18 : ($resource === 'competencies' ? Competency::count() : 1)), 'data');
        }

        $period->update(['status' => 'Cerrado']);
        $this->putJson('/api/v1/evaluations/'.$evaluationId.'/scores', ['records' => [['enrollment_id' => $enrollment->id, 'score' => 20]]])
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
    }

    public function test_primary_competencies_are_available_for_evaluations_without_duplicates(): void
    {
        $english = Course::where('name', 'Inglés')->firstOrFail();
        $before = Competency::count();
        $this->seed(CompetenciasPrimariaSeeder::class);

        $this->assertSame($before, Competency::count());
        $this->getJson('/api/v1/management/competencies?course_id='.$english->id)
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $period = AcademicPeriod::where('status', 'Activo')->firstOrFail();
        $classroom = Classroom::where('year', 2026)->where('grade', 3)->where('section', 'B')->firstOrFail();
        $competency = Competency::where('course_id', $english->id)->whereNull('grade')->firstOrFail();
        $this->postJson('/api/v1/management/evaluations', [
            'academic_period_id' => $period->id,
            'classroom_id' => $classroom->id,
            'course_id' => $english->id,
            'competency_id' => $competency->id,
            'title' => 'Evaluación de inglés',
            'type' => 'Examen',
            'evaluation_date' => $period->starts_on->format('Y-m-d'),
            'max_score' => 20,
            'weight' => 100,
            'status' => 'Programada',
        ])->assertCreated()->assertJsonPath('data.competency_id', $competency->id);
    }

    public function test_a_classroom_has_one_teacher_for_all_courses_and_periods(): void
    {
        $aula = Classroom::where('grade', 5)->where('section', 'A')->where('year', 2026)->firstOrFail();
        $anterior = TeacherAssignment::where('classroom_id', $aula->id)->firstOrFail();
        $nueva = Teacher::create([
            'name' => 'Prof. Aurora Ramírez', 'dni' => '49999999', 'email' => 'aurora@example.test',
            'status' => 'Activo', 'color' => '#3b82f6',
        ]);

        $this->postJson('/api/v1/management/teacher-assignments', [
            'teacher_id' => $nueva->id, 'classroom_id' => $aula->id, 'status' => 'Activo',
        ])->assertUnprocessable()->assertJsonValidationErrors('classroom_id');

        $this->putJson('/api/v1/management/teacher-assignments/'.$anterior->id, [
            'teacher_id' => $anterior->teacher_id, 'classroom_id' => $aula->id, 'status' => 'Inactivo',
        ])->assertUnprocessable()->assertJsonValidationErrors('classroom_id');

        $this->putJson('/api/v1/management/teacher-assignments/'.$anterior->id, [
            'teacher_id' => $nueva->id, 'classroom_id' => $aula->id, 'status' => 'Activo',
        ])->assertOk();

        $cursos = $aula->courses()->count();
        $periodos = AcademicPeriod::whereHas('academicYear', fn ($q) => $q->where('year', 2026))->count();
        $this->assertSame($cursos * $periodos, TeacherAssignment::where('teacher_id', $nueva->id)
            ->where('classroom_id', $aula->id)->where('status', 'Activo')->count());
        $this->assertDatabaseHas('schedules', ['classroom_id' => $aula->id, 'teacher_id' => $nueva->id]);

        $otraAula = Classroom::where('year', 2026)->whereKeyNot($aula->id)->firstOrFail();
        $this->postJson('/api/v1/management/teacher-assignments', [
            'teacher_id' => $nueva->id, 'classroom_id' => $otraAula->id, 'status' => 'Activo',
        ])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
    }
}
