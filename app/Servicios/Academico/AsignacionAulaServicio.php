<?php

namespace App\Servicios\Academico;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Una docente titular por aula y año. Las filas por curso y periodo se mantienen
 * como detalle para las consultas académicas existentes.
 */
class AsignacionAulaServicio
{
    public function guardar(array $datos, ?int $id = null): TeacherAssignment
    {
        return DB::transaction(function () use ($datos, $id) {
            $aula = Classroom::query()->lockForUpdate()->findOrFail($datos['classroom_id']);
            $docente = Teacher::query()->lockForUpdate()->findOrFail($datos['teacher_id']);
            $original = $id ? TeacherAssignment::findOrFail($id) : null;
            $cursos = $aula->courses()->get(['courses.id', 'courses.hours']);
            $periodos = AcademicPeriod::whereHas('academicYear', fn ($consulta) => $consulta->where('year', $aula->year))->get();

            $this->verificar($docente->status === 'Activo', 'teacher_id', 'La docente debe estar activa.');
            $this->verificar($cursos->isNotEmpty(), 'classroom_id', 'Asigna cursos al aula antes de asignar una docente.');
            $this->verificar($periodos->isNotEmpty(), 'classroom_id', 'Crea los periodos del año antes de asignar una docente.');

            $excluir = $original
                ? TeacherAssignment::where('teacher_id', $original->teacher_id)->where('classroom_id', $original->classroom_id)->pluck('id')
                : collect();
            $activas = TeacherAssignment::query()->where('status', 'Activo')
                ->whereHas('classroom', fn ($consulta) => $consulta->where('year', $aula->year))
                ->when($excluir->isNotEmpty(), fn ($consulta) => $consulta->whereNotIn('id', $excluir));

            $this->verificar(
                ! (clone $activas)->where('teacher_id', $docente->id)->where('classroom_id', '!=', $aula->id)->exists(),
                'teacher_id',
                'Esta docente ya tiene otra aula activa en el año académico.'
            );
            if ($datos['status'] === 'Activo') {
                $this->verificar(
                    ! (clone $activas)->where('classroom_id', $aula->id)->where('teacher_id', '!=', $docente->id)->exists(),
                    'classroom_id',
                    'Esta aula ya tiene una docente activa. Desactiva primero su asignación.'
                );
            }
            $this->verificar(
                ! TeacherAssignment::where('teacher_id', $docente->id)->where('classroom_id', $aula->id)
                    ->when($excluir->isNotEmpty(), fn ($consulta) => $consulta->whereNotIn('id', $excluir))->exists(),
                'teacher_id',
                'La docente ya tiene un registro para esta aula. Edita la asignación existente.'
            );

            if ($original && $datos['status'] !== 'Activo') {
                $this->verificar(
                    ! Schedule::where('classroom_id', $original->classroom_id)
                        ->where('teacher_id', $original->teacher_id)->exists(),
                    'classroom_id',
                    'El aula tiene clases de esta docente. Reasigna o retira esos bloques antes de desactivar la titularidad.'
                );
            }

            if ($original) {
                TeacherAssignment::where('teacher_id', $original->teacher_id)
                    ->where('classroom_id', $original->classroom_id)->delete();
            }

            $primera = null;
            foreach ($periodos as $periodo) {
                foreach ($cursos as $curso) {
                    $asignacion = TeacherAssignment::create([
                        'teacher_id' => $docente->id,
                        'classroom_id' => $aula->id,
                        'course_id' => $curso->id,
                        'academic_period_id' => $periodo->id,
                        'weekly_hours' => $curso->hours,
                        'status' => $datos['status'],
                    ]);
                    $primera ??= $asignacion;
                }
            }

            if ($datos['status'] === 'Activo') {
                $docente->courses()->syncWithoutDetaching($cursos->pluck('id'));
                Schedule::where('classroom_id', $aula->id)->update(['teacher_id' => $docente->id]);
            }

            return $primera->load(['teacher:id,name', 'classroom:id,year,grade,section']);
        }, 3);
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id) {
            $asignacion = TeacherAssignment::findOrFail($id);
            $this->verificar(
                ! Schedule::where('classroom_id', $asignacion->classroom_id)->where('teacher_id', $asignacion->teacher_id)->exists(),
                'classroom_id',
                'Esta docente figura en el horario. Reasigna o elimina esos bloques antes de borrar la asignación.'
            );
            TeacherAssignment::where('teacher_id', $asignacion->teacher_id)
                ->where('classroom_id', $asignacion->classroom_id)->delete();
        });
    }

    public function sincronizarCurso(\App\Models\Course $curso): void
    {
        foreach ($curso->classrooms as $aula) {
            $titular = TeacherAssignment::where('classroom_id', $aula->id)
                ->where('status', 'Activo')->first();
            if (! $titular) {
                continue;
            }
            $periodos = AcademicPeriod::whereHas('academicYear', fn ($q) => $q->where('year', $aula->year))->get();
            foreach ($periodos as $periodo) {
                TeacherAssignment::updateOrCreate(
                    ['teacher_id' => $titular->teacher_id, 'course_id' => $curso->id,
                        'classroom_id' => $aula->id, 'academic_period_id' => $periodo->id],
                    ['weekly_hours' => $curso->hours, 'status' => 'Activo']
                );
            }
            $titular->teacher->courses()->syncWithoutDetaching([$curso->id]);
        }
    }

    public function sincronizarPeriodo(AcademicPeriod $periodo): void
    {
        foreach (Classroom::where('year', $periodo->academicYear->year)->get() as $aula) {
            $titular = TeacherAssignment::where('classroom_id', $aula->id)
                ->where('status', 'Activo')->first();
            if (! $titular) {
                continue;
            }
            foreach ($aula->courses as $curso) {
                TeacherAssignment::updateOrCreate(
                    ['teacher_id' => $titular->teacher_id, 'course_id' => $curso->id,
                        'classroom_id' => $aula->id, 'academic_period_id' => $periodo->id],
                    ['weekly_hours' => $curso->hours, 'status' => 'Activo']
                );
            }
        }
    }

    private function verificar(bool $condicion, string $campo, string $mensaje): void
    {
        if (! $condicion) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }
}
