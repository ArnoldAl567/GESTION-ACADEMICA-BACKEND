<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Communication;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos de demostración solo se instalan en local o testing.');
        }
        DB::transaction(function () {
            $admin = User::firstOrCreate(['email' => 'admin@colegio.edu.pe'], ['name' => 'Admin. General', 'password' => 'Admin2026!', 'role' => 'admin']);
            $teacherUser = User::firstOrCreate(['email' => 'docente@colegio.edu.pe'], ['name' => 'Marta Villanueva', 'password' => 'Docente2026!', 'role' => 'docente']);
            $parent = User::firstOrCreate(['email' => 'padre@colegio.edu.pe'], ['name' => 'Carmen Espinoza', 'password' => 'Padre2026!', 'role' => 'padre']);
            if (! DB::table('school_settings')->where('id', 1)->exists()) {
                DB::table('school_settings')->insert(['id' => 1, 'name' => 'I.E. San Martín de Porres', 'year' => 2026, 'period' => 3, 'created_at' => now(), 'updated_at' => now()]);
            }
            $academicYear = AcademicYear::firstOrCreate(['year' => 2026], ['starts_on' => '2026-03-01', 'ends_on' => '2026-12-20', 'status' => 'Activo']);
            foreach ([['03-01', '05-10'], ['05-11', '07-26'], ['08-05', '10-11'], ['10-14', '12-20']] as $index => [$start, $end]) {
                $number = $index + 1;
                AcademicPeriod::firstOrCreate(
                    ['academic_year_id' => $academicYear->id, 'number' => $number],
                    ['name' => 'Bimestre '.$number, 'starts_on' => '2026-'.$start, 'ends_on' => '2026-'.$end, 'status' => $number === 3 ? 'Activo' : ($number < 3 ? 'Cerrado' : 'Planificado')]
                );
            }
            $rooms = collect();
            foreach (range(1, 6) as $grade) {
                foreach (['A', 'B', 'C'] as $section) {
                    $rooms->push(Classroom::firstOrCreate(['year' => 2026, 'grade' => $grade, 'section' => $section], ['capacity' => 30]));
                }
            }
            $teacherNames = ['Marta Villanueva', 'Ricardo Solano', 'Diana Campos', 'Carmen Huayta', 'Luis Mendoza', 'Ana Salinas', 'Rosa Inga', 'Manuel Torres', 'Jorge Aliaga', 'Pedro Choque'];
            $courseNames = ['Comunicación', 'Matemática', 'Ciencia y Tecnología', 'Personal Social', 'Educación Física', 'Arte y Cultura', 'Inglés', 'Educación Religiosa'];
            $colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#06b6d4', '#a78bfa'];
            $soft = ['#eff6ff', '#ecfdf5', '#f5f3ff', '#fffbeb', '#fef2f2', '#fdf2f8', '#ecfeff', '#f5f3ff'];
            $courses = collect();
            foreach ($teacherNames as $index => $name) {
                $teacher = Teacher::firstOrCreate(['dni' => (string) (40000000 + $index)], ['name' => 'Prof. '.$name, 'email' => $index === 0 ? $teacherUser->email : 'docente'.($index + 1).'@colegio.edu.pe', 'phone' => '987654321', 'user_id' => $index === 0 ? $teacherUser->id : null, 'status' => 'Activo', 'color' => $colors[$index % 8]]);
                if ($index < 8) {
                    $course = Course::firstOrCreate(['name' => $courseNames[$index]], ['category' => $courseNames[$index], 'description' => 'Desarrollo de competencias del área de '.$courseNames[$index].'.', 'hours' => $index < 2 ? 6 : 4, 'color' => $colors[$index], 'soft' => $soft[$index]]);
                    $course->teachers()->syncWithoutDetaching([$teacher->id]);
                    $course->classrooms()->syncWithoutDetaching($rooms->pluck('id'));
                    $courses->push($course);
                }
            }
            $names = ['Valentina Ríos Espinoza', 'Mateo Fernández Quiroz', 'Luciana Paredes Vega', 'Sebastián Torres Lima', 'Isabella Morales Chávez', 'Alejandro Huanca Quispe', 'Sofía Mamani Flores', 'Diego Castillo Rojas', 'Xiomara Condori Lamas', 'Kevin Palomino Roca', 'Camila Salazar Ruiz', 'Adrián Vargas Peña', 'Lucía Sánchez Díaz', 'Gabriel Flores Ramos', 'Emma Mendoza León', 'Nicolás Castro Soto', 'Martina Vega Paz', 'Joaquín Rojas Lara', 'Daniela Ponce Silva', 'Samuel Ortiz Luna'];
            foreach ($names as $index => $name) {
                $student = Student::firstOrCreate(['dni' => (string) (75000000 + $index)], ['name' => $name, 'guardian' => $index === 0 ? 'Carmen Espinoza' : 'Apoderado de '.$name, 'guardian_user_id' => $index === 0 ? $parent->id : null, 'phone' => '987654321', 'status' => 'Activo', 'color' => $colors[$index % 8]]);
                $room = $index < 2 ? $rooms->first(fn ($r) => $r->grade === 5 && $r->section === 'A') : $rooms[$index % $rooms->count()];
                $enrollment = Enrollment::firstOrCreate(['student_id' => $student->id, 'year' => 2026], ['classroom_id' => $room->id, 'enrolled_at' => '2026-03-02', 'status' => $index === 4 ? 'Pendiente' : 'Activo']);
                if ($enrollment->status !== 'Activo') {
                    continue;
                }
                foreach ($courses as $courseIndex => $course) {
                    Grade::firstOrCreate(['enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'period' => 3], ['score' => 9 + (($index + $courseIndex) % 11), 'recorded_by' => $admin->id]);
                }
                foreach (['2026-08-24', '2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28'] as $dayIndex => $date) {
                    Attendance::firstOrCreate(['enrollment_id' => $enrollment->id, 'date' => $date], ['status' => ($index + $dayIndex) % 13 === 0 ? 'F' : 'P', 'recorded_by' => $admin->id]);
                }
            }
            $room = $rooms->first(fn ($r) => $r->grade === 5 && $r->section === 'A');
            foreach (range(1, 5) as $day) {
                foreach ([['07:30', '08:15'], ['08:15', '09:00'], ['09:00', '09:45'], ['10:00', '10:45'], ['10:45', '11:30'], ['11:30', '12:15']] as $index => $times) {
                    $course = $courses[($index + $day - 1) % 8];
                    Schedule::firstOrCreate(['classroom_id' => $room->id, 'day' => $day, 'starts_at' => $times[0]], ['ends_at' => $times[1], 'course_id' => $course->id, 'teacher_id' => $course->teachers()->value('teachers.id')]);
                }
            }
            foreach (['Reunión de padres de familia — Tercer Bimestre', 'Día del Logro — Exposición de proyectos', 'Simulacro de sismo', 'Calendario de evaluaciones', 'Entrega de libretas', 'Inscripciones a talleres'] as $index => $subject) {
                Communication::firstOrCreate(['subject' => $subject, 'author_id' => $admin->id], ['type' => ['Comunicado', 'Circular', 'Urgente'][$index % 3], 'body' => 'Estimadas familias: les invitamos a participar en las actividades de nuestra comunidad educativa. Consulten con la dirección para conocer los detalles y horarios de '.$subject.'.', 'audience' => 'all', 'draft' => false, 'published_at' => now()->subDays($index)]);
            }
        });
    }
}
