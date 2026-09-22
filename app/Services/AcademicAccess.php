<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Communication;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AcademicAccess
{
    public function admin(User $user): void
    {
        abort_unless($user->active, 401);
        abort_unless($user->role === 'admin', 403, 'Se requiere el perfil Administrador.');
    }

    public function staff(User $user): void
    {
        abort_unless($user->active, 401);
        abort_unless(in_array($user->role, ['admin', 'docente']), 403, 'Acción reservada al personal docente.');
    }

    public function classroomIds(User $user): array
    {
        abort_unless($user->active, 401);
        if ($user->role === 'admin') {
            return Classroom::pluck('id')->all();
        }
        if ($user->role === 'docente') {
            return Classroom::whereHas('courses.teachers', fn (Builder $q) => $q->where('user_id', $user->id))->pluck('id')->all();
        }

        return Enrollment::whereHas('student', fn (Builder $q) => $q->where('guardian_user_id', $user->id))->pluck('classroom_id')->unique()->values()->all();
    }

    public function students(User $user): Builder
    {
        abort_unless($user->active, 401);
        $query = Student::query();
        if ($user->role === 'padre') {
            $query->where('guardian_user_id', $user->id);
        } elseif ($user->role === 'docente') {
            $query->whereHas('enrollments', fn (Builder $q) => $q->whereIn('classroom_id', $this->classroomIds($user)));
        }

        return $query;
    }

    public function enrollments(User $user): Builder
    {
        $query = Enrollment::whereIn('student_id', $this->students($user)->select('students.id'));
        if ($user->role === 'docente') {
            $query->whereIn('classroom_id', $this->classroomIds($user));
        }

        return $query;
    }

    public function courses(User $user): Builder
    {
        $query = Course::query();
        if ($user->role === 'docente') {
            $query->whereHas('teachers', fn (Builder $q) => $q->where('user_id', $user->id));
        } elseif ($user->role === 'padre') {
            $query->whereHas('classrooms', fn (Builder $q) => $q->whereIn('classrooms.id', $this->classroomIds($user)));
        }

        return $query;
    }

    public function teachers(User $user): Builder
    {
        if ($user->role === 'admin') {
            return Teacher::query();
        }

        $courseIds = $this->courses($user)->pluck('courses.id');

        return Teacher::whereHas('courses', fn (Builder $q) => $q->whereIn('courses.id', $courseIds));
    }

    public function communications(User $user): Builder
    {
        abort_unless($user->active, 401);

        return Communication::query()->where(function (Builder $q) use ($user) {
            $q->where('author_id', $user->id)->orWhere(function (Builder $visible) use ($user) {
                $visible->where('draft', false)->whereNotNull('published_at')->where('published_at', '<=', now())
                    ->whereIn('audience', ['all', $user->role])
                    ->where(fn (Builder $scope) => $scope->whereNull('classroom_id')->orWhereIn('classroom_id', $this->classroomIds($user)));
            });
        });
    }

    public function classroom(User $user, int $classroomId): void
    {
        abort_unless(in_array($classroomId, $this->classroomIds($user)), 403, 'No tienes acceso a esta aula.');
    }

    public function grade(User $user, int $courseId, int $classroomId): void
    {
        $this->staff($user);
        $this->classroom($user, $classroomId);
        abort_unless($this->courses($user)->whereKey($courseId)->exists(), 403, 'No tienes asignado este curso.');
    }
}
