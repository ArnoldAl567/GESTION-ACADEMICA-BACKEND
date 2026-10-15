<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Communication;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AcademicAccess
{
    public function admin(User $user): void
    {
        abort_unless($user->active, 401);
        abort_unless($user->accessScope() === 'admin', 403, 'Se requiere el perfil Administrador.');
    }

    public function staff(User $user): void
    {
        abort_unless($user->active, 401);
        abort_unless(in_array($user->accessScope(), ['admin', 'docente']), 403, 'Acción reservada al personal docente.');
    }

    public function classroomIds(User $user): array
    {
        abort_unless($user->active, 401);
        if ($user->accessScope() === 'admin') {
            return Classroom::pluck('id')->all();
        }
        if ($user->accessScope() === 'docente') {
            return TeacherAssignment::where('status', 'Activo')
                ->whereHas('teacher', fn (Builder $q) => $q->where('user_id', $user->id)->where('status', 'Activo'))
                ->distinct()->pluck('classroom_id')->all();
        }

        return Enrollment::whereHas('student', fn (Builder $q) => $q->where('guardian_user_id', $user->id))->pluck('classroom_id')->unique()->values()->all();
    }

    public function students(User $user): Builder
    {
        abort_unless($user->active, 401);
        $query = Student::query();
        if ($user->accessScope() === 'padre') {
            $query->where('guardian_user_id', $user->id);
        } elseif ($user->accessScope() === 'docente') {
            $query->whereHas('enrollments', fn (Builder $q) => $q->whereIn('classroom_id', $this->classroomIds($user)));
        }

        return $query;
    }

    public function enrollments(User $user): Builder
    {
        $query = Enrollment::whereIn('student_id', $this->students($user)->select('students.id'));
        if ($user->accessScope() === 'docente') {
            $query->whereIn('classroom_id', $this->classroomIds($user));
        }

        return $query;
    }

    public function courses(User $user): Builder
    {
        $query = Course::query();
        if ($user->accessScope() === 'docente') {
            $query->whereIn('id', TeacherAssignment::where('status', 'Activo')
                ->whereHas('teacher', fn (Builder $q) => $q->where('user_id', $user->id))
                ->select('course_id'));
        } elseif ($user->accessScope() === 'padre') {
            $query->whereHas('classrooms', fn (Builder $q) => $q->whereIn('classrooms.id', $this->classroomIds($user)));
        }

        return $query;
    }

    public function teachers(User $user): Builder
    {
        if ($user->accessScope() === 'admin') {
            return Teacher::query();
        }

        return Teacher::whereHas('assignments', fn (Builder $q) => $q->where('status', 'Activo')
            ->whereIn('classroom_id', $this->classroomIds($user)));
    }

    public function communications(User $user): Builder
    {
        abort_unless($user->active, 401);

        return Communication::query()->where(function (Builder $q) use ($user) {
            $q->where('author_id', $user->id)->orWhere(function (Builder $visible) use ($user) {
                $visible->where('draft', false)->whereNotNull('published_at')->where('published_at', '<=', now())
                    ->whereIn('audience', ['all', $user->accessScope()])
                    ->where(fn (Builder $scope) => $scope->whereNull('classroom_id')->orWhereIn('classroom_id', $this->classroomIds($user)));
            });
        });
    }

    public function classroom(User $user, int $classroomId): void
    {
        abort_unless(in_array($classroomId, $this->classroomIds($user)), 403, 'No tienes acceso a esta aula.');
    }

    public function schedule(User $user, int $classroomId): void
    {
        $this->staff($user);
        $this->classroom($user, $classroomId);
        if ($user->accessScope() === 'admin') {
            return;
        }

        $year = Classroom::whereKey($classroomId)->value('year');
        abort_unless(AcademicYear::where('year', $year)->where('status', 'Activo')->exists()
            && TeacherAssignment::where('classroom_id', $classroomId)->where('status', 'Activo')
                ->whereHas('teacher', fn (Builder $query) => $query
                    ->where('user_id', $user->id)->where('status', 'Activo'))->exists(),
            403, 'Solo puedes gestionar el horario de tu aula y año académico activos.');
    }

    public function attendance(User $user, int $classroomId, string $date): void
    {
        $this->staff($user);
        $this->classroom($user, $classroomId);
        if ($user->accessScope() === 'admin') {
            return;
        }

        $year = Classroom::whereKey($classroomId)->value('year');
        $periodId = AcademicPeriod::where('status', 'Activo')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->whereHas('academicYear', fn (Builder $query) => $query
                ->where('year', $year)->where('status', 'Activo'))
            ->value('id');
        abort_unless($periodId && TeacherAssignment::where('status', 'Activo')
            ->where('classroom_id', $classroomId)->where('academic_period_id', $periodId)
            ->whereHas('teacher', fn (Builder $query) => $query
                ->where('user_id', $user->id)->where('status', 'Activo'))
            ->exists(), 403, 'Solo puedes registrar asistencia en tu aula y periodo activos.');
    }

    public function grade(User $user, int $courseId, int $classroomId, ?int $periodId = null): void
    {
        $this->staff($user);
        $this->classroom($user, $classroomId);
        if ($user->accessScope() === 'admin') {
            return;
        }
        abort_unless($periodId !== null && TeacherAssignment::where('status', 'Activo')
            ->where('classroom_id', $classroomId)->where('course_id', $courseId)
            ->where('academic_period_id', $periodId)
            ->whereHas('teacher', fn (Builder $q) => $q->where('user_id', $user->id))->exists(), 403,
            'No tienes asignado este curso, aula y periodo.');
    }
}
