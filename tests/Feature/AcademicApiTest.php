<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Communication;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\AcademicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicApiTest extends TestCase
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

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function studentData(): array
    {
        return Student::factory()->make()->toArray();
    }

    public function test_authentication_uses_the_role_associated_with_the_credentials(): void
    {
        $this->getJson('/api/v1/students')->assertUnauthorized();
        $this->postJson('/api/v1/login', ['email' => 'admin@colegio.edu.pe', 'password' => 'incorrect'])->assertUnprocessable();
        $this->postJson('/api/v1/login', ['email' => 'admin@colegio.edu.pe', 'password' => 'Admin2026!'])->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonMissingPath('user.password');
        $this->postJson('/api/v1/logout')->assertOk();
        $this->postJson('/api/v1/login', ['email' => 'docente@colegio.edu.pe', 'password' => 'Docente2026!'])->assertOk()->assertJsonPath('user.role', 'docente');
        $this->postJson('/api/v1/logout')->assertOk();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_rejects_accounts_with_an_inactive_role(): void
    {
        Role::where('code', 'docente')->update(['active' => false]);

        $this->postJson('/api/v1/login', ['email' => 'docente@colegio.edu.pe', 'password' => 'Docente2026!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_default_admin_access_works_only_when_enabled(): void
    {
        $this->postJson('/api/v1/default-admin')
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.email', 'admin@colegio.edu.pe')
            ->assertJsonMissingPath('user.password');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.role', 'admin');

        $this->postJson('/api/v1/logout')->assertOk();
        config(['auth.default_admin_access' => false]);
        $this->postJson('/api/v1/default-admin')->assertNotFound();
    }

    public function test_all_module_queries_use_postgresql(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->actingAs($this->admin());
        foreach (['bootstrap', 'dashboard?year=2026', 'students', 'guardians', 'roles', 'teachers', 'courses', 'classrooms', 'enrollments', 'schedules', 'communications', 'academic-years', 'academic-periods', 'users'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertOk();
        }
        $this->getJson('/api/v1/bootstrap')->assertJsonCount(4, 'periods');
        foreach (['academic', 'attendance', 'students', 'enrollment', 'teachers'] as $type) {
            $this->getJson('/api/v1/reports?type='.$type.'&year=2026&period=3')->assertOk()->assertJsonStructure(['data' => ['rows', 'columns', 'summary']]);
        }
    }

    public function test_student_create_update_search_delete_and_duplicate_validation(): void
    {
        $this->actingAs($this->admin());
        $data = $this->studentData();
        $this->postJson('/api/v1/students', [...$data, 'sex' => 'M'])->assertUnprocessable()->assertJsonValidationErrors('sex');
        $id = $this->postJson('/api/v1/students', $data)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('students', ['id' => $id, 'dni' => $data['dni'], 'sex' => 'F']);
        $this->postJson('/api/v1/students', $data)->assertUnprocessable()->assertJsonValidationErrors('dni');
        $data['name'] = 'Alumna actualizada';
        $this->putJson('/api/v1/students/'.$id, $data)->assertOk();
        $this->getJson('/api/v1/students?q=actualizada')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/students?q='.$data['dni'])->assertOk()->assertJsonPath('data.0.id', $id);
        $this->deleteJson('/api/v1/students/'.$id)->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $id]);

        $anterior = Student::factory()->create(['sex' => null]);
        $datosAnteriores = $anterior->only(['name', 'dni', 'guardian', 'phone', 'status']);
        $this->putJson('/api/v1/students/'.$anterior->id, $datosAnteriores)->assertOk();
        $this->assertDatabaseHas('students', ['id' => $anterior->id, 'sex' => null]);
    }

    public function test_guardian_crud_links_students_and_keeps_legacy_contact_in_sync(): void
    {
        $this->actingAs($this->admin());
        $account = User::factory()->create(['name' => 'Rosa Elena Mendoza', 'email' => 'rosa.mendoza@example.com', 'role' => 'padre', 'active' => true]);
        $guardian = [
            'name' => 'Rosa Elena Mendoza',
            'dni' => '44556677',
            'phone' => '987654300',
            'email' => 'rosa.mendoza@example.com',
            'address' => 'Av. Los Laureles 125',
            'user_id' => $account->id,
            'status' => 'Activo',
        ];
        $this->postJson('/api/v1/guardians', array_merge($guardian, ['dni' => null, 'user_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dni', 'user_id']);
        $guardianId = $this->postJson('/api/v1/guardians', $guardian)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/guardians?q=44556677')->assertOk()->assertJsonPath('data.0.id', $guardianId);

        $student = array_merge($this->studentData(), ['guardian_id' => $guardianId]);
        $studentId = $this->postJson('/api/v1/students', $student)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('students', ['id' => $studentId, 'guardian_id' => $guardianId, 'guardian' => $guardian['name'], 'phone' => $guardian['phone']]);
        $this->getJson('/api/v1/guardians/'.$guardianId)->assertOk()->assertJsonPath('data.students_count', 1);

        $guardian['name'] = 'Rosa Mendoza Actualizada';
        $guardian['phone'] = '900111222';
        $this->putJson('/api/v1/guardians/'.$guardianId, $guardian)->assertOk();
        $this->assertDatabaseHas('students', ['id' => $studentId, 'guardian' => $guardian['name'], 'phone' => $guardian['phone']]);
        $this->deleteJson('/api/v1/guardians/'.$guardianId)->assertConflict();

        $this->deleteJson('/api/v1/students/'.$studentId)->assertOk();
        $this->deleteJson('/api/v1/guardians/'.$guardianId)->assertOk();
        $this->assertDatabaseMissing('guardians', ['id' => $guardianId]);
    }

    public function test_students_can_be_filtered_by_grade_and_section(): void
    {
        $this->actingAs($this->admin());
        $room = Classroom::whereHas('enrollments')->orderBy('grade')->orderBy('section')->firstOrFail();
        $expectedSection = Enrollment::where('year', $room->year)->where('classroom_id', $room->id)->distinct()->count('student_id');
        $expectedGrade = Student::whereHas('enrollments', fn ($query) => $query
            ->where('year', $room->year)
            ->whereHas('classroom', fn ($classrooms) => $classrooms->where('grade', $room->grade)))
            ->count();

        $this->getJson('/api/v1/students?year='.$room->year.'&grade='.$room->grade)
            ->assertOk()
            ->assertJsonPath('total', $expectedGrade);
        $this->getJson('/api/v1/students?year='.$room->year.'&grade='.$room->grade.'&classroom_id='.$room->id)
            ->assertOk()
            ->assertJsonPath('total', $expectedSection);
    }

    public function test_student_with_history_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin())->deleteJson('/api/v1/students/'.Student::first()->id)->assertConflict();
    }

    public function test_parent_only_sees_own_child_and_cannot_write(): void
    {
        $parent = User::where('role', 'padre')->firstOrFail();
        $this->actingAs($parent)->getJson('/api/v1/students')->assertOk()->assertJsonPath('total', 1);
        $other = Student::where('guardian_user_id', '!=', $parent->id)->firstOrFail();
        $this->getJson('/api/v1/students?q='.$other->dni)->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/students/'.$other->id)->assertNotFound();
        $this->postJson('/api/v1/students', $this->studentData())->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
        $room = Classroom::where('grade', 1)->firstOrFail();
        $this->getJson('/api/v1/grades?classroom_id='.$room->id.'&period=3')->assertForbidden();
    }

    public function test_enrollment_rejects_duplicate_capacity_and_wrong_year(): void
    {
        $this->actingAs($this->admin());
        $student = Student::factory()->create();
        $room = Classroom::factory()->create(['capacity' => 1]);
        $data = ['student_id' => $student->id, 'classroom_id' => $room->id, 'year' => 2026, 'enrolled_at' => '2026-03-01', 'status' => 'Activo'];
        $this->postJson('/api/v1/enrollments', $data)->assertCreated();
        $this->postJson('/api/v1/enrollments', $data)->assertUnprocessable();
        $data['student_id'] = Student::factory()->create()->id;
        $this->postJson('/api/v1/enrollments', $data)->assertUnprocessable()->assertJsonValidationErrors('classroom_id');
        $data['year'] = 2027;
        $this->postJson('/api/v1/enrollments', $data)->assertUnprocessable();
    }

    public function test_enrollments_can_be_filtered_by_grade_and_section(): void
    {
        $this->actingAs($this->admin());
        $room = Classroom::whereHas('enrollments')->orderBy('grade')->orderBy('section')->firstOrFail();
        $expectedGrade = Enrollment::where('year', $room->year)
            ->whereHas('classroom', fn ($classrooms) => $classrooms->where('grade', $room->grade))
            ->count();
        $expectedSection = Enrollment::where('year', $room->year)
            ->where('classroom_id', $room->id)
            ->count();

        $this->getJson('/api/v1/enrollments?year='.$room->year.'&grade='.$room->grade)
            ->assertOk()
            ->assertJsonPath('total', $expectedGrade);
        $this->getJson('/api/v1/enrollments?year='.$room->year.'&grade='.$room->grade.'&classroom_id='.$room->id)
            ->assertOk()
            ->assertJsonPath('total', $expectedSection);
    }

    public function test_enrollment_status_can_be_toggled(): void
    {
        $this->actingAs($this->admin());
        $enrollment = Enrollment::where('status', 'Activo')->firstOrFail();

        $this->putJson('/api/v1/enrollments/'.$enrollment->id.'/activation', ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'Pendiente');
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'Pendiente']);

        $this->putJson('/api/v1/enrollments/'.$enrollment->id.'/activation', ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'Activo');
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'Activo']);
    }

    public function test_enrollment_candidates_only_include_active_students_without_enrollment_for_the_year(): void
    {
        $this->actingAs($this->admin());
        $year = Enrollment::firstOrFail()->year;
        $candidate = Student::factory()->create(['status' => 'Activo']);
        $inactive = Student::factory()->create(['status' => 'Inactivo']);
        $alreadyEnrolled = Enrollment::where('year', $year)->firstOrFail()->student_id;

        $response = $this->getJson('/api/v1/enrollment-candidates?year='.$year)->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($candidate->id));
        $this->assertFalse($ids->contains($inactive->id));
        $this->assertFalse($ids->contains($alreadyEnrolled));
    }

    public function test_attendance_is_persistent_idempotent_and_atomic(): void
    {
        $this->actingAs($this->admin());
        $e = Enrollment::where('status', 'Activo')->firstOrFail();
        $date = '2026-09-01';
        $data = ['classroom_id' => $e->classroom_id, 'date' => $date, 'records' => [['enrollment_id' => $e->id, 'status' => 'T']]];
        $this->putJson('/api/v1/attendance', $data)->assertOk();
        $data['records'][0]['status'] = 'P';
        $this->putJson('/api/v1/attendance', $data)->assertOk();
        $this->assertSame(1, Attendance::where('enrollment_id', $e->id)->where('date', $date)->count());
        $foreign = Enrollment::where('classroom_id', '!=', $e->classroom_id)->firstOrFail();
        $data['records'][0]['status'] = 'F';
        $data['records'][] = ['enrollment_id' => $foreign->id, 'status' => 'F'];
        $this->putJson('/api/v1/attendance', $data)->assertUnprocessable();
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $e->id, 'date' => $date, 'status' => 'P']);
    }

    public function test_grades_enforce_range_and_teacher_ownership(): void
    {
        $teacher = User::where('role', 'docente')->firstOrFail();
        $assignment = TeacherAssignment::where('status', 'Activo')
            ->whereHas('teacher', fn ($q) => $q->where('user_id', $teacher->id))
            ->whereHas('academicPeriod', fn ($q) => $q->where('number', 3))
            ->firstOrFail();
        $own = Course::findOrFail($assignment->course_id);
        $other = Course::where('id', '!=', $own->id)
            ->whereHas('classrooms', fn ($q) => $q->whereKey($assignment->classroom_id))
            ->firstOrFail();
        $e = Enrollment::where('status', 'Activo')->where('classroom_id', $assignment->classroom_id)->firstOrFail();
        $data = ['classroom_id' => $e->classroom_id, 'course_id' => $own->id, 'period' => 3, 'records' => [['enrollment_id' => $e->id, 'score' => 18.5]]];
        $this->actingAs($teacher)->putJson('/api/v1/grades', $data)->assertOk();
        $this->assertDatabaseHas('grades', ['enrollment_id' => $e->id, 'course_id' => $own->id, 'score' => 18.5]);
        $data['records'][0]['score'] = null;
        $this->putJson('/api/v1/grades', $data)->assertOk();
        $this->assertDatabaseMissing('grades', ['enrollment_id' => $e->id, 'course_id' => $own->id, 'period' => 3]);
        $data['records'][0]['score'] = 21;
        $this->putJson('/api/v1/grades', $data)->assertUnprocessable();
        $data['records'][0]['score'] = 19;
        $data['course_id'] = $other->id;
        $this->putJson('/api/v1/grades', $data)->assertOk();
        $data['classroom_id'] = Classroom::where('year', 2026)->where('id', '!=', $e->classroom_id)->firstOrFail()->id;
        $this->putJson('/api/v1/grades', $data)->assertForbidden();
    }

    public function test_schedule_rejects_overlapping_teacher_or_classroom(): void
    {
        $this->actingAs($this->admin());
        $course = Course::firstOrFail();
        $rooms = $course->classrooms()->orderBy('id')->take(2)->get();
        $data = ['classroom_id' => $rooms[0]->id, 'course_id' => $course->id, 'teacher_id' => $rooms[0]->activeAssignment->teacher_id, 'day' => 1, 'starts_at' => '14:00', 'ends_at' => '14:45'];
        $this->postJson('/api/v1/schedules', $data)->assertCreated();
        $data['starts_at'] = '14:30';
        $this->postJson('/api/v1/schedules', $data)->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $data['classroom_id'] = $rooms[1]->id;
        $data['teacher_id'] = $rooms[1]->activeAssignment->teacher_id;
        $data['ends_at'] = '15:30';
        $this->postJson('/api/v1/schedules', $data)->assertCreated();
        $data['teacher_id'] = $rooms[0]->activeAssignment->teacher_id;
        $this->postJson('/api/v1/schedules', $data)->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
    }

    public function test_communications_respect_audience_drafts_and_per_user_receipts(): void
    {
        $this->actingAs($this->admin());
        $data = ['type' => 'Comunicado', 'subject' => 'Solo docentes', 'body' => 'Información docente', 'audience' => 'docente', 'draft' => false];
        $id = $this->postJson('/api/v1/communications', $data)->assertCreated()->json('data.id');
        $parent = User::where('role', 'padre')->firstOrFail();
        $this->actingAs($parent)->getJson('/api/v1/communications/'.$id)->assertNotFound();
        $this->putJson('/api/v1/communications/'.$id.'/receipt', [])->assertNotFound();
        $public = Communication::where('audience', 'all')->firstOrFail();
        $this->putJson('/api/v1/communications/'.$public->id.'/receipt', ['archived' => true])->assertOk();
        $this->assertDatabaseHas('communication_user', ['user_id' => $parent->id, 'communication_id' => $public->id, 'archived' => true]);
        $this->assertDatabaseMissing('communication_user', ['user_id' => $this->admin()->id, 'communication_id' => $public->id]);
        $data['draft'] = true;
        $data['audience'] = 'all';
        $id = $this->actingAs($this->admin())->postJson('/api/v1/communications', $data)->assertCreated()->json('data.id');
        $this->actingAs($parent)->getJson('/api/v1/communications/'.$id)->assertNotFound();
    }

    public function test_dashboard_changes_after_database_writes_and_reports_export(): void
    {
        $this->actingAs($this->admin());
        $before = $this->getJson('/api/v1/dashboard?year=2026&period=3')->assertOk()->json('data.students');
        $student = Student::factory()->create();
        $room = Classroom::firstOrFail();
        $this->postJson('/api/v1/enrollments', ['student_id' => $student->id, 'classroom_id' => $room->id, 'year' => 2026, 'enrolled_at' => '2026-04-01', 'status' => 'Activo'])->assertCreated();
        $this->getJson('/api/v1/dashboard?year=2026&period=3')->assertOk()->assertJsonPath('data.students', $before + 1);
        $this->getJson('/api/v1/reports/export?type=students&year=2026&period=3')->assertOk()->assertDownload('reporte-students-2026.csv');
    }

    public function test_user_accounts_settings_and_disabled_login(): void
    {
        $this->actingAs($this->admin());
        $id = $this->postJson('/api/v1/users', ['name' => 'Nuevo docente', 'email' => 'nuevo@example.test', 'role' => 'docente', 'password' => 'Secure2026!'])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/users/'.$id, ['active' => false])->assertOk();
        $this->putJson('/api/v1/settings', ['name' => 'Colegio de prueba', 'year' => 2027, 'period' => 1])->assertOk();
        $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('settings.year', 2027);
        $this->postJson('/api/v1/logout');
        $this->postJson('/api/v1/login', ['email' => 'nuevo@example.test', 'password' => 'Secure2026!'])->assertUnprocessable();
    }

    public function test_roles_can_be_managed_and_protect_base_or_assigned_roles(): void
    {
        $this->actingAs($this->admin());

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.system', true);

        $data = [
            'code' => 'coordinador',
            'name' => 'Coordinador académico',
            'description' => 'Supervisa el trabajo académico.',
            'scope' => 'docente',
            'icon' => 'supervisor_account',
            'active' => true,
        ];
        $roleId = $this->postJson('/api/v1/roles', $data)
            ->assertCreated()
            ->assertJsonPath('data.system', false)
            ->json('data.id');

        $data['name'] = 'Coordinación académica';
        $this->putJson('/api/v1/roles/'.$roleId, $data)
            ->assertOk()
            ->assertJsonPath('data.name', $data['name']);
        $this->putJson('/api/v1/roles/'.$roleId.'/activation', ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);
        $this->putJson('/api/v1/roles/'.$roleId.'/activation', ['active' => true])->assertOk();

        User::factory()->create(['role' => 'coordinador']);
        $this->deleteJson('/api/v1/roles/'.$roleId)->assertUnprocessable();
        $this->deleteJson('/api/v1/roles/'.Role::where('code', 'docente')->value('id'))->assertUnprocessable();
        $this->putJson('/api/v1/roles/'.Role::where('code', 'admin')->value('id').'/activation', ['active' => false])
            ->assertUnprocessable();

        $freeRoleId = Role::create([
            'code' => 'auxiliar',
            'name' => 'Auxiliar',
            'description' => 'Apoya el registro académico.',
            'scope' => 'docente',
            'icon' => 'support_agent',
            'active' => true,
        ])->id;
        $this->deleteJson('/api/v1/roles/'.$freeRoleId)->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $freeRoleId]);
    }

    public function test_disabled_account_loses_access_to_existing_session(): void
    {
        $parent = User::where('role', 'padre')->firstOrFail();
        $this->actingAs($parent)->getJson('/api/v1/bootstrap')->assertOk();
        $parent->update(['active' => false]);
        $this->getJson('/api/v1/bootstrap')->assertUnauthorized();
        $this->getJson('/api/v1/students')->assertUnauthorized();
    }

    public function test_teacher_cannot_read_same_students_history_in_unassigned_year(): void
    {
        $teacher = User::where('role', 'docente')->firstOrFail();
        $student = Student::firstOrFail();
        $room = Classroom::factory()->create(['year' => 2027]);
        $enrollment = Enrollment::create(['student_id' => $student->id, 'classroom_id' => $room->id, 'year' => 2027, 'enrolled_at' => '2027-03-01', 'status' => 'Activo']);
        $this->actingAs($teacher)->getJson('/api/v1/enrollments/'.$enrollment->id)->assertNotFound();
        $this->getJson('/api/v1/reports?type=enrollment&year=2027&period=1')->assertOk()->assertJsonCount(0, 'data.rows');
    }

    public function test_teacher_and_course_crud_persist_and_keep_references_valid(): void
    {
        $this->actingAs($this->admin());
        $teacherData = ['name' => 'Docente de prueba', 'dni' => '45678901', 'email' => 'prueba@example.test', 'status' => 'Activo'];
        $teacherId = $this->postJson('/api/v1/teachers', $teacherData)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/teachers', $teacherData)->assertUnprocessable();
        $teacherData['name'] = 'Docente actualizado';
        $this->putJson('/api/v1/teachers/'.$teacherId, $teacherData)->assertOk();
        $room = Classroom::firstOrFail();
        $courseData = ['name' => 'Robótica', 'category' => 'Ciencias', 'hours' => 3, 'classroom_ids' => [$room->id]];
        $courseId = $this->postJson('/api/v1/courses', $courseData)->assertCreated()->json('data.id');
        $this->assertSame(4, TeacherAssignment::where('course_id', $courseId)->where('classroom_id', $room->id)->count());
        $this->assertDatabaseHas('teacher_assignments', ['course_id' => $courseId, 'classroom_id' => $room->id, 'teacher_id' => $room->activeAssignment->teacher_id]);
        $this->assertDatabaseHas('classroom_course', ['classroom_id' => $room->id, 'course_id' => $courseId]);
        $courseData['hours'] = 5;
        $this->putJson('/api/v1/courses/'.$courseId, $courseData)->assertOk()->assertJsonPath('data.hours', 5);
        $this->assertDatabaseHas('teacher_assignments', ['course_id' => $courseId, 'classroom_id' => $room->id, 'weekly_hours' => 5]);
        $this->deleteJson('/api/v1/courses/'.$courseId)->assertOk();
        $this->deleteJson('/api/v1/teachers/'.$teacherId)->assertOk();
    }

    public function test_new_year_classrooms_and_password_change(): void
    {
        $this->actingAs($this->admin());
        $data = ['year' => 2028, 'grade' => 1, 'section' => 'A', 'capacity' => 25];
        $id = $this->postJson('/api/v1/classrooms', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/classrooms', $data)->assertUnprocessable();
        $this->getJson('/api/v1/classrooms?year=2028&grade=1&q=A')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.courses_count', 0);
        $data['capacity'] = 30;
        $this->putJson('/api/v1/classrooms/'.$id, $data)->assertOk();
        $this->deleteJson('/api/v1/classrooms/'.$id)->assertOk();
        $this->putJson('/api/v1/password', ['current_password' => 'incorrect', 'password' => 'NuevaClave2026!', 'password_confirmation' => 'NuevaClave2026!'])->assertUnprocessable();
        $this->putJson('/api/v1/password', ['current_password' => 'Admin2026!', 'password' => 'NuevaClave2026!', 'password_confirmation' => 'NuevaClave2026!'])->assertOk();
        $this->postJson('/api/v1/logout')->assertOk();
        $this->postJson('/api/v1/login', ['email' => 'admin@colegio.edu.pe', 'password' => 'NuevaClave2026!'])->assertOk();
    }

    public function test_academic_year_and_period_crud(): void
    {
        $this->actingAs($this->admin());
        $yearData = ['year' => 2030, 'starts_on' => '2030-03-01', 'ends_on' => '2030-12-20', 'status' => 'Planificado'];
        $yearId = $this->postJson('/api/v1/academic-years', $yearData)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/academic-years', $yearData)->assertUnprocessable()->assertJsonValidationErrors('year');

        $periodData = ['academic_year_id' => $yearId, 'number' => 1, 'name' => 'Primer bimestre', 'starts_on' => '2030-03-01', 'ends_on' => '2030-05-10', 'status' => 'Planificado'];
        $periodId = $this->postJson('/api/v1/academic-periods', $periodData)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/academic-periods', $periodData)->assertUnprocessable()->assertJsonValidationErrors('number');
        $periodData['name'] = 'Bimestre 1';
        $this->putJson('/api/v1/academic-periods/'.$periodId, $periodData)->assertOk()->assertJsonPath('data.name', 'Bimestre 1');
        $this->getJson('/api/v1/academic-periods?academic_year_id='.$yearId)->assertOk()->assertJsonPath('total', 1);
        $this->deleteJson('/api/v1/academic-periods/'.$periodId)->assertOk();
        $this->deleteJson('/api/v1/academic-years/'.$yearId)->assertOk();

        $activeYearId = DB::table('academic_years')->where('status', 'Activo')->value('id');
        $this->deleteJson('/api/v1/academic-years/'.$activeYearId)->assertUnprocessable();
    }

    public function test_only_one_academic_year_and_period_can_be_active(): void
    {
        $this->actingAs($this->admin());
        $previousYear = AcademicYear::where('status', 'Activo')->firstOrFail();
        $previousPeriod = AcademicPeriod::where('status', 'Activo')->firstOrFail();
        $newYear = AcademicYear::create([
            'year' => 2031,
            'starts_on' => '2031-03-01',
            'ends_on' => '2031-12-20',
            'status' => 'Planificado',
        ]);
        $newPeriod = AcademicPeriod::create([
            'academic_year_id' => $newYear->id,
            'number' => 1,
            'name' => 'Bimestre 1',
            'starts_on' => '2031-03-01',
            'ends_on' => '2031-05-10',
            'status' => 'Planificado',
        ]);

        $this->putJson('/api/v1/academic-years/'.$newYear->id.'/activation', ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'Activo');
        $this->assertDatabaseCount('academic_years', AcademicYear::count());
        $this->assertSame(1, AcademicYear::where('status', 'Activo')->count());
        $this->assertDatabaseMissing('academic_years', ['id' => $previousYear->id, 'status' => 'Activo']);
        $this->assertDatabaseMissing('academic_periods', ['id' => $previousPeriod->id, 'status' => 'Activo']);

        $this->putJson('/api/v1/academic-periods/'.$newPeriod->id.'/activation', ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'Activo');
        $this->assertSame(1, AcademicYear::where('status', 'Activo')->count());
        $this->assertSame(1, AcademicPeriod::where('status', 'Activo')->count());
        $this->assertDatabaseHas('academic_years', ['id' => $newYear->id, 'status' => 'Activo']);
        $this->assertDatabaseHas('school_settings', ['id' => 1, 'year' => 2031, 'period' => 1]);

        $this->putJson('/api/v1/academic-years/'.$newYear->id.'/activation', ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'Planificado');
        $this->assertSame(0, AcademicYear::where('status', 'Activo')->count());
        $this->assertSame(0, AcademicPeriod::where('status', 'Activo')->count());
    }
}
