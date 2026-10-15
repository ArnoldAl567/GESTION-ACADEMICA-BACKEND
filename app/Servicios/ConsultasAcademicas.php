<?php

namespace App\Servicios;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AcademicAccess;
use Illuminate\Database\Eloquent\Builder;

class ConsultasAcademicas
{
    public function __construct(private AcademicAccess $acceso) {}

    public function paraUsuario(User $usuario, string $recurso): Builder
    {
        abort_unless($usuario->active, 401);

        return match ($recurso) {
            'students' => $this->acceso->students($usuario)->with(['enrollments.classroom', 'guardianRecord']),
            'guardians' => $this->apoderadosParaUsuario($usuario),
            'teachers' => $usuario->accessScope() === 'padre'
                ? $this->acceso->teachers($usuario)->select('id', 'name', 'status')->with('assignments.classroom')
                : $this->acceso->teachers($usuario)->with(['courses.classrooms', 'assignments.classroom']),
            'courses' => $usuario->accessScope() === 'admin'
                ? $this->acceso->courses($usuario)->with(['teachers', 'classrooms'])
                : $this->acceso->courses($usuario)->with('classrooms'),
            'classrooms' => Classroom::whereIn('id', $this->acceso->classroomIds($usuario))->with('activeAssignment.teacher:id,name')->withCount([
                'courses',
                'enrollments' => fn (Builder $query) => $query->whereIn('status', ['Activo', 'Pendiente']),
            ]),
            'enrollments' => $this->acceso->enrollments($usuario)->with(['student', 'classroom']),
            'schedules' => Schedule::whereIn('classroom_id', $this->acceso->classroomIds($usuario))
                ->with($usuario->accessScope() === 'padre'
                    ? ['course', 'teacher:id,name,status', 'classroom']
                    : ['course', 'teacher', 'classroom']),
            'communications' => $this->acceso->communications($usuario)->with([
                'author:id,name',
                'readers' => fn ($query) => $query->where('users.id', $usuario->id),
            ]),
            'academic-years' => AcademicYear::query()->withCount('periods'),
            'academic-periods' => AcademicPeriod::query()->with('academicYear'),
            default => abort(404),
        };
    }

    private function apoderadosParaUsuario(User $usuario): Builder
    {
        $query = Guardian::query();
        if ($usuario->accessScope() === 'padre') {
            return $query->where('user_id', $usuario->id)->withCount('students');
        }
        if ($usuario->accessScope() === 'docente') {
            $aulas = $this->acceso->classroomIds($usuario);

            return $query->select('id', 'name', 'phone', 'email', 'status')
                ->whereHas('students.enrollments', fn (Builder $matriculas) => $matriculas
                    ->whereIn('classroom_id', $aulas)->where('status', 'Activo'))
                ->withCount(['students' => fn (Builder $alumnas) => $alumnas
                    ->whereHas('enrollments', fn (Builder $matriculas) => $matriculas
                        ->whereIn('classroom_id', $aulas)->where('status', 'Activo'))]);
        }

        return $query->with('user:id,name,email')->withCount('students');
    }
}
