<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\AcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.connections.pgsql.database') !== 'gestion_academica_test') {
            throw new \RuntimeException('Las pruebas solo pueden migrar gestion_academica_test.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicSeeder::class);
    }

    public function test_permissions_are_exposed_and_enforced_by_action(): void
    {
        $docente = User::where('role', 'docente')->firstOrFail();
        $this->actingAs($docente)->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('permissions.attendance.1', 'update')
            ->assertJsonPath('permissions.roles', []);
        $this->getJson('/api/v1/guardians')->assertOk()
            ->assertJsonMissingPath('data.0.dni')
            ->assertJsonMissingPath('data.0.user');
        $this->postJson('/api/v1/enrollments', [])->assertForbidden();
        $this->deleteJson('/api/v1/management/evaluations/1')->assertForbidden();
        $this->getJson('/api/v1/management/administrative-staff')->assertForbidden();
        $this->getJson('/api/v1/reports?type=students&year=2026&period=3')->assertOk();

        $familia = User::where('role', 'padre')->firstOrFail();
        $this->actingAs($familia)->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('permissions.guardians.0', 'viewOwn')
            ->assertJsonPath('permissions.guardians.1', 'updateOwn')
            ->assertJsonPath('permissions.reports', [])
            ->assertJsonPath('permissions.evaluations', [])
            ->assertJsonMissingPath('teachers.0.email');
        $this->getJson('/api/v1/reports?type=students&year=2026&period=3')->assertForbidden();
        $this->getJson('/api/v1/management/student-observations')->assertForbidden();
        $this->putJson('/api/v1/attendance', [])->assertForbidden();
    }

    public function test_guardian_can_only_edit_own_contact_details(): void
    {
        $familia = User::where('role', 'padre')->firstOrFail();
        $apoderado = Guardian::where('user_id', $familia->id)->firstOrFail();
        $otro = Guardian::where('user_id', '!=', $familia->id)->firstOrFail();

        $this->actingAs($familia)->getJson('/api/v1/guardian-profile')
            ->assertOk()->assertJsonPath('data.id', $apoderado->id)
            ->assertJsonPath('data.students_count', $apoderado->students()->count());
        $this->getJson('/api/v1/guardians')->assertForbidden();
        $this->getJson('/api/v1/guardians/'.$otro->id)->assertForbidden();
        $this->putJson('/api/v1/guardians/'.$otro->id, ['phone' => '900000001'])->assertForbidden();
        $this->putJson('/api/v1/guardian-profile', [
            'phone' => '900000001', 'email' => 'contacto@example.test', 'address' => 'Jr. Las Flores 123',
            'name' => 'Nombre no autorizado', 'user_id' => $otro->user_id,
        ])->assertOk();
        $this->assertDatabaseHas('guardians', ['id' => $apoderado->id, 'name' => $apoderado->name, 'phone' => '900000001']);
        $this->assertDatabaseHas('students', ['guardian_id' => $apoderado->id, 'phone' => '900000001']);
        $this->assertDatabaseHas('guardians', ['id' => $otro->id, 'phone' => $otro->phone]);
    }

    public function test_guardian_cannot_open_institutional_maintainers(): void
    {
        $familia = User::where('role', 'padre')->firstOrFail();
        $this->actingAs($familia);

        foreach ([
            'guardians', 'teachers', 'academic-years', 'academic-periods',
            'classrooms', 'courses', 'management/competencies', 'management/evaluations',
        ] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden();
        }

        $this->getJson('/api/v1/management/academic-events')->assertOk();
    }

    public function test_family_sees_only_public_teacher_details_in_own_schedule(): void
    {
        $aula = Classroom::where('year', 2026)->where('grade', 5)->where('section', 'A')->firstOrFail();
        $matricula = Enrollment::where('classroom_id', $aula->id)->where('status', 'Activo')->firstOrFail();
        $familia = User::findOrFail($matricula->student->guardian_user_id);

        $this->actingAs($familia)->getJson('/api/v1/schedules?classroom_id='.$aula->id)
            ->assertOk()->assertJsonPath('data.0.teacher.name', $aula->activeAssignment->teacher->name)
            ->assertJsonMissingPath('data.0.teacher.email')
            ->assertJsonMissingPath('data.0.teacher.phone');
    }

    public function test_teacher_attendance_requires_the_active_assigned_period(): void
    {
        $docente = User::where('role', 'docente')->firstOrFail();
        $this->assertSame('docente', $docente->accessScope());
        AcademicPeriod::where('number', 1)->update(['status' => 'Cerrado']);
        AcademicPeriod::where('number', 3)->update(['status' => 'Activo']);
        $aulaId = TeacherAssignment::where('status', 'Activo')
            ->whereHas('teacher', fn ($query) => $query->where('user_id', $docente->id))
            ->value('classroom_id');
        $matricula = Enrollment::where('classroom_id', $aulaId)->where('status', 'Activo')->firstOrFail();
        $registros = [['enrollment_id' => $matricula->id, 'status' => 'P']];

        $this->actingAs($docente)->putJson('/api/v1/attendance', [
            'classroom_id' => $aulaId, 'date' => '2026-03-02', 'records' => $registros,
        ])->assertForbidden();
        $this->putJson('/api/v1/attendance', [
            'classroom_id' => $aulaId, 'date' => '2026-08-24', 'records' => $registros,
        ])->assertOk();
    }

    public function test_teacher_manages_only_the_schedule_of_her_assigned_classroom(): void
    {
        $marta = User::where('role', 'docente')->firstOrFail();
        $aula = Classroom::where('year', 2026)->where('grade', 5)->where('section', 'A')->firstOrFail();
        $otraAula = Classroom::where('year', 2026)->whereKeyNot($aula->id)->firstOrFail();
        $curso = $aula->courses()->firstOrFail();
        $titular = $aula->activeAssignment->teacher;

        $this->actingAs($marta)->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('permissions.schedule', ['view', 'create', 'update', 'delete'])
            ->assertJsonCount(1, 'classrooms')
            ->assertJsonPath('classrooms.0.id', $aula->id);

        $bloque = [
            'classroom_id' => $aula->id,
            'course_id' => $curso->id,
            'day' => 1,
            'starts_at' => '13:00',
            'ends_at' => '13:45',
        ];
        $this->postJson('/api/v1/schedules', [...$bloque, 'classroom_id' => $otraAula->id])->assertForbidden();
        $id = $this->postJson('/api/v1/schedules', $bloque)
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $titular->id)
            ->json('data.id');
        $this->putJson('/api/v1/schedules/'.$id, [...$bloque, 'course_id' => $curso->id, 'ends_at' => '14:00'])
            ->assertOk()->assertJsonPath('data.teacher_id', $titular->id);
        $this->putJson('/api/v1/schedules/'.$id, [...$bloque, 'classroom_id' => $otraAula->id])->assertForbidden();
        $this->deleteJson('/api/v1/schedules/'.$id)->assertOk();
        $this->assertDatabaseMissing('schedules', ['id' => $id]);

        $ajeno = Schedule::create([
            'classroom_id' => $otraAula->id,
            'course_id' => $curso->id,
            'teacher_id' => $otraAula->activeAssignment->teacher_id,
            'day' => 1,
            'starts_at' => '14:00',
            'ends_at' => '14:45',
        ]);
        $this->deleteJson('/api/v1/schedules/'.$ajeno->id)->assertNotFound();
    }

    public function test_family_only_receives_published_results_for_own_child(): void
    {
        $aula = Classroom::where('year', 2026)->where('grade', 5)->where('section', 'A')->firstOrFail();
        $matriculas = Enrollment::where('classroom_id', $aula->id)->where('status', 'Activo')->orderBy('id')->take(2)->get();
        $this->assertCount(2, $matriculas);
        $periodo = AcademicPeriod::whereHas('academicYear', fn ($year) => $year->where('year', 2026))
            ->where('number', 3)->firstOrFail();
        $curso = Course::whereHas('classrooms', fn ($room) => $room->whereKey($aula->id))->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $evaluacion = Evaluation::create([
            'academic_period_id' => $periodo->id, 'classroom_id' => $aula->id,
            'course_id' => $curso->id, 'title' => 'Resultado de prueba', 'type' => 'Práctica',
            'evaluation_date' => $periodo->starts_on->format('Y-m-d'),
            'max_score' => 20, 'weight' => 50, 'status' => 'Calificada',
        ]);
        foreach ($matriculas as $matricula) {
            EvaluationScore::create([
                'evaluation_id' => $evaluacion->id, 'enrollment_id' => $matricula->id,
                'score' => 17, 'recorded_by' => $admin->id,
            ]);
        }

        $familia = User::findOrFail($matriculas[0]->student->guardian_user_id);
        $this->actingAs($familia)->getJson('/api/v1/management/evaluations')->assertForbidden();
        $this->getJson('/api/v1/report-cards?classroom_id='.$aula->id.'&academic_period_id='.$periodo->id)
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/grades?classroom_id='.$aula->id.'&period=3')
            ->assertOk()->assertJsonCount(0, 'data.0.grades');

        $periodo->update(['status' => 'Cerrado']);
        $this->getJson('/api/v1/report-cards?classroom_id='.$aula->id.'&academic_period_id='.$periodo->id)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.evaluation_scores')
            ->assertJsonPath('data.0.evaluation_scores.0.enrollment_id', $matriculas[0]->id);
        $this->getJson('/api/v1/report-cards/'.$matriculas[1]->id.'/pdf?academic_period_id='.$periodo->id)
            ->assertNotFound();
    }
}
