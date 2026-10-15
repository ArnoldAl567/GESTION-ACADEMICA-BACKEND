<?php

namespace App\Servicios\Academico;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Teacher;
use Illuminate\Validation\ValidationException;

class ReglasAcademicasServicio
{
    public function validarCursoAulaPeriodo(array $data): void
    {
        $course = Course::findOrFail($data['course_id']);
        $classroom = Classroom::findOrFail($data['classroom_id']);
        $period = AcademicPeriod::with('academicYear')->findOrFail($data['academic_period_id']);

        $this->asegurar(
            $course->classrooms()->whereKey($classroom->id)->exists(),
            'course_id',
            'El curso no está asignado al grado y sección seleccionados.'
        );
        $this->asegurar(
            (int) $period->academicYear->year === (int) $classroom->year,
            'academic_period_id',
            'El periodo no corresponde al año del grado y sección.'
        );
    }

    public function validarAsignacionDocente(array $data): void
    {
        $this->validarCursoAulaPeriodo($data);
        $teacher = Teacher::findOrFail($data['teacher_id']);
        $course = Course::findOrFail($data['course_id']);

        $this->asegurar($teacher->status === 'Activo', 'teacher_id', 'El docente debe estar activo.');
        $this->asegurar(
            $course->teachers()->whereKey($teacher->id)->exists(),
            'teacher_id',
            'El docente no está vinculado al curso seleccionado.'
        );
    }

    public function validarEvento(array $data): void
    {
        if (empty($data['classroom_id'])) {
            return;
        }

        $classroom = Classroom::findOrFail($data['classroom_id']);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $this->asegurar(
            (int) $classroom->year === (int) $year->year,
            'classroom_id',
            'El grado y sección no pertenece al año académico seleccionado.'
        );
    }

    public function validarObservacion(array $data): Enrollment
    {
        $enrollment = Enrollment::findOrFail($data['enrollment_id']);
        $period = AcademicPeriod::with('academicYear')->findOrFail($data['academic_period_id']);
        $this->asegurar(
            (int) $enrollment->year === (int) $period->academicYear->year,
            'academic_period_id',
            'El periodo no corresponde a la matrícula.'
        );
        $this->asegurar(
            $period->status !== 'Cerrado',
            'academic_period_id',
            'El periodo está cerrado y ya no admite modificaciones.'
        );

        return $enrollment;
    }

    private function asegurar(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
