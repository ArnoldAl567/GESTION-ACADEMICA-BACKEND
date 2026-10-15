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
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
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
            if (! DB::table('school_settings')->where('id', 1)->exists()) {
                DB::table('school_settings')->insert(['id' => 1, 'name' => 'Institución Educativa N° 80404', 'year' => 2026, 'period' => 3, 'created_at' => now(), 'updated_at' => now()]);
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
            $teacherNames = ['Marta Villanueva', 'Rosa Inga', 'Diana Campos', 'Carmen Huayta', 'Ana Salinas', 'Elena Quispe', 'María Torres', 'Lucía Aliaga', 'Patricia Choque', 'Julia Salazar', 'Verónica Vega', 'Sonia Paredes', 'Claudia Ríos', 'Teresa Mamani', 'Isabel Flores', 'Gabriela Huamán', 'Norma Castillo', 'Paola Mendoza'];
            $courseNames = ['Comunicación', 'Matemática', 'Ciencia y Tecnología', 'Personal Social', 'Educación Física', 'Arte y Cultura', 'Inglés', 'Educación Religiosa'];
            $colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#06b6d4', '#a78bfa'];
            $soft = ['#eff6ff', '#ecfdf5', '#f5f3ff', '#fffbeb', '#fef2f2', '#fdf2f8', '#ecfeff', '#f5f3ff'];
            $courses = collect();
            $docentes = collect();
            foreach ($teacherNames as $index => $name) {
                $teacher = Teacher::firstOrCreate(['dni' => (string) (40000000 + $index)], ['name' => 'Prof. '.$name, 'email' => $index === 0 ? $teacherUser->email : 'docente'.($index + 1).'@colegio.edu.pe', 'phone' => '987654321', 'user_id' => $index === 0 ? $teacherUser->id : null, 'status' => 'Activo', 'color' => $colors[$index % 8]]);
                $docentes->push($teacher);
            }
            foreach ($courseNames as $index => $name) {
                $course = Course::firstOrCreate(['name' => $name], ['category' => $name, 'description' => 'Desarrollo de competencias del área de '.$name.'.', 'hours' => $index < 2 ? 6 : 4, 'color' => $colors[$index], 'soft' => $soft[$index]]);
                $course->classrooms()->syncWithoutDetaching($rooms->pluck('id'));
                $courses->push($course);
            }
            $aulaQuintoA = $rooms->first(fn ($aula) => $aula->grade === 5 && $aula->section === 'A');
            $aulasOrdenadas = $rooms->reject(fn ($aula) => $aula->id === $aulaQuintoA->id)->prepend($aulaQuintoA)->values();
            $docentePorAula = [];
            $periodos = AcademicPeriod::where('academic_year_id', $academicYear->id)->get();
            foreach ($aulasOrdenadas as $indiceAula => $aula) {
                $docente = $docentes[$indiceAula];
                $docentePorAula[$aula->id] = $docente;
                $docente->courses()->syncWithoutDetaching($courses->pluck('id'));
                foreach ($periodos as $periodo) {
                    foreach ($courses as $curso) {
                        TeacherAssignment::firstOrCreate(
                            ['teacher_id' => $docente->id, 'course_id' => $curso->id, 'classroom_id' => $aula->id, 'academic_period_id' => $periodo->id],
                            ['weekly_hours' => $curso->hours, 'status' => 'Activo']
                        );
                    }
                }
            }
            $names = ['Valentina Ríos Espinoza', 'Mariana Fernández Quiroz', 'Luciana Paredes Vega', 'Sebastiana Torres Lima', 'Isabella Morales Chávez', 'Alejandra Huanca Quispe', 'Sofía Mamani Flores', 'Diana Castillo Rojas', 'Xiomara Condori Lamas', 'Karla Palomino Roca', 'Camila Salazar Ruiz', 'Adriana Vargas Peña', 'Lucía Sánchez Díaz', 'Gabriela Flores Ramos', 'Emma Mendoza León', 'Nicole Castro Soto', 'Martina Vega Paz', 'Joaquina Rojas Lara', 'Daniela Ponce Silva', 'Samantha Ortiz Luna'];
            $guardianProfiles = [
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
            ];
            foreach ($names as $index => $name) {
                [$guardianName, $guardianDni, $guardianEmail] = $guardianProfiles[$index];
                $guardianUser = User::firstOrCreate(
                    ['email' => $guardianEmail],
                    ['name' => $guardianName, 'password' => 'Familia2026!', 'role' => 'padre', 'active' => true]
                );
                $guardian = Guardian::updateOrCreate(
                    ['dni' => $guardianDni],
                    ['name' => $guardianName, 'phone' => '987654321', 'email' => $guardianEmail, 'user_id' => $guardianUser->id, 'status' => 'Activo']
                );
                $student = Student::firstOrCreate(['dni' => (string) (75000000 + $index)], ['name' => $name, 'sex' => 'F', 'guardian' => $guardianName, 'guardian_user_id' => $guardianUser->id, 'guardian_id' => $guardian->id, 'phone' => $guardian->phone, 'status' => 'Activo', 'color' => $colors[$index % 8]]);
                $student->update(['guardian' => $guardianName, 'guardian_user_id' => $guardianUser->id, 'guardian_id' => $guardian->id, 'phone' => $guardian->phone]);
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
                    Schedule::firstOrCreate(['classroom_id' => $room->id, 'day' => $day, 'starts_at' => $times[0]], ['ends_at' => $times[1], 'course_id' => $course->id, 'teacher_id' => $docentePorAula[$room->id]->id]);
                }
            }
            foreach (['Reunión de padres de familia — Tercer Bimestre', 'Día del Logro — Exposición de proyectos', 'Simulacro de sismo', 'Calendario de evaluaciones', 'Entrega de libretas', 'Inscripciones a talleres'] as $index => $subject) {
                Communication::firstOrCreate(['subject' => $subject, 'author_id' => $admin->id], ['type' => ['Comunicado', 'Circular', 'Urgente'][$index % 3], 'body' => 'Estimadas familias: les invitamos a participar en las actividades de nuestra comunidad educativa. Consulten con la dirección para conocer los detalles y horarios de '.$subject.'.', 'audience' => 'all', 'draft' => false, 'published_at' => now()->subDays($index)]);
            }
        });
    }
}
