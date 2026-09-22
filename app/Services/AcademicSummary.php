<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AcademicSummary
{
    public function __construct(private AcademicAccess $access) {}

    public function dashboard(User $user, int $year, int $period): array
    {
        $enrollments = $this->access->enrollments($user)->where('year', $year)->with(['student', 'classroom', 'grades' => fn ($q) => $q->where('period', $period), 'attendances'])->get();
        $active = $enrollments->where('status', 'Activo');
        $grades = $active->flatMap->grades;
        if ($user->role === 'docente') {
            $courseIds = $this->access->courses($user)->pluck('id')->all();
            $grades = $grades->whereIn('course_id', $courseIds);
        }
        $attendance = $active->flatMap->attendances;
        $present = $attendance->whereIn('status', ['P', 'T'])->count();
        $messages = $this->access->communications($user)->where('draft', false)->whereDoesntHave('readers', fn (Builder $q) => $q->where('users.id', $user->id)->whereNotNull('read_at'))->count();
        $distribution = collect(range(1, 6))->map(fn ($grade) => ['grade' => $grade, 'total' => $active->filter(fn ($e) => (int) $e->classroom->grade === $grade)->count()])->all();
        $performance = collect(range(1, 6))->map(function ($grade) use ($active, $grades) {
            $ids = $active->filter(fn ($e) => (int) $e->classroom->grade === $grade)->pluck('id');

            return ['grade' => $grade, 'average' => round((float) $grades->whereIn('enrollment_id', $ids)->avg('score'), 2)];
        })->all();
        $weekly = $attendance->groupBy('date')->sortKeys()->map(fn ($rows, $date) => ['date' => $date, 'present' => $rows->whereIn('status', ['P', 'T'])->count(), 'absent' => $rows->where('status', 'F')->count(), 'total' => $rows->count()])->values()->take(-7)->values()->all();
        $trend = $enrollments->groupBy(fn ($e) => substr($e->enrolled_at, 0, 7))->sortKeys()->map(fn ($rows, $month) => ['month' => $month, 'total' => $rows->count()])->values()->all();
        $alerts = $active->map(function ($e) use ($grades) {
            $studentGrades = $grades->where('enrollment_id', $e->id);
            $avg = $studentGrades->avg('score');
            $rate = $e->attendances->count() ? 100 * $e->attendances->whereIn('status', ['P', 'T'])->count() / $e->attendances->count() : null;

            return ['student' => $e->student->name, 'average' => $avg === null ? null : round($avg, 2), 'attendance' => $rate === null ? null : round($rate, 1), 'classroom' => $e->classroom->grade.'° '.$e->classroom->section];
        })->filter(fn ($r) => ($r['average'] !== null && $r['average'] < 10.5) || ($r['attendance'] !== null && $r['attendance'] < 80))->values()->all();

        return [
            'students' => $enrollments->pluck('student_id')->unique()->count(),
            'teachers' => $this->access->teachers($user)->where('status', 'Activo')->count(),
            'enrollments' => $active->count(),
            'pending' => $enrollments->where('status', 'Pendiente')->count(),
            'cancelled' => $enrollments->where('status', 'Cancelado')->count(),
            'attendance' => $attendance->count() ? round(100 * $present / $attendance->count(), 1) : 0,
            'average' => round((float) $grades->avg('score'), 2),
            'unread' => $messages,
            'distribution' => $distribution,
            'performance' => $performance,
            'weekly' => $weekly,
            'trend' => $trend,
            'alerts' => $alerts,
            'activity' => $enrollments->sortByDesc('updated_at')->take(6)->map(fn ($e) => ['description' => 'Matrícula: '.$e->student->name.' — '.$e->status, 'date' => $e->updated_at->toIso8601String()])->values()->all(),
        ];
    }

    public function report(User $user, string $type, int $year, int $period): array
    {
        abort_unless($user->active, 401);
        $enrollments = $this->access->enrollments($user)->where('year', $year);
        $ids = (clone $enrollments)->pluck('id');
        $result = match ($type) {
            'academic' => [
                'columns' => ['Estudiante', 'Grado', 'Sección', 'Curso', 'Bimestre', 'Nota'],
                'rows' => Grade::whereIn('enrollment_id', $ids)->where('period', $period)->whereIn('course_id', $this->access->courses($user)->pluck('id'))->with(['enrollment.student', 'enrollment.classroom', 'course'])->orderBy('enrollment_id')->get()->map(fn ($g) => [$g->enrollment->student->name, $g->enrollment->classroom->grade, $g->enrollment->classroom->section, $g->course->name, $g->period, $g->score])->all(),
            ],
            'attendance' => [
                'columns' => ['Estudiante', 'Grado', 'Sección', 'Fecha', 'Estado'],
                'rows' => Attendance::whereIn('enrollment_id', $ids)->with(['enrollment.student', 'enrollment.classroom'])->orderByDesc('date')->get()->map(fn ($a) => [$a->enrollment->student->name, $a->enrollment->classroom->grade, $a->enrollment->classroom->section, $a->date, $a->status])->all(),
            ],
            'students' => [
                'columns' => ['Estudiante', 'DNI', 'Apoderado', 'Teléfono', 'Estado'],
                'rows' => $this->access->students($user)->whereIn('id', (clone $enrollments)->select('student_id'))->orderBy('name')->get()->map(fn ($s) => [$s->name, $s->dni, $s->guardian, $s->phone, $s->status])->all(),
            ],
            'enrollment' => [
                'columns' => ['Matrícula', 'Estudiante', 'Año', 'Grado', 'Sección', 'Fecha', 'Estado'],
                'rows' => $enrollments->with(['student', 'classroom'])->orderBy('id')->get()->map(fn ($e) => [$e->id, $e->student->name, $e->year, $e->classroom->grade, $e->classroom->section, $e->enrolled_at, $e->status])->all(),
            ],
            'teachers' => [
                'columns' => ['Docente', 'Correo', 'Cursos que dicta', 'Horas semanales', 'Estado'],
                'rows' => $this->access->teachers($user)->with(['courses' => fn ($q) => $q->whereHas('classrooms', fn ($room) => $room->where('year', $year))])->get()->map(fn ($t) => [$t->name, $t->email, $t->courses->pluck('name')->join(', '), $t->courses->sum('hours'), $t->status])->all(),
            ],
        };

        return array_merge($result, ['type' => $type, 'year' => $year, 'period' => $period, 'summary' => $this->dashboard($user, $year, $period)]);
    }
}
