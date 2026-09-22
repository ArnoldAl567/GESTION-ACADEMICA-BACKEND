<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicRequest;
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
use App\Services\AcademicAccess;
use App\Services\AcademicSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicController extends Controller
{
    private const MODELS = ['students' => Student::class, 'teachers' => Teacher::class, 'courses' => Course::class, 'classrooms' => Classroom::class, 'enrollments' => Enrollment::class, 'schedules' => Schedule::class, 'communications' => Communication::class, 'academic-years' => AcademicYear::class, 'academic-periods' => AcademicPeriod::class];

    public function __construct(private AcademicAccess $access, private AcademicSummary $summary) {}

    private function query(Request $request, string $resource): Builder
    {
        abort_unless($request->user()?->active, 401);
        $user = $request->user();

        return match ($resource) {
            'students' => $this->access->students($user)->with(['enrollments.classroom']),
            'teachers' => $this->access->teachers($user)->with(['courses.classrooms']),
            'courses' => $this->access->courses($user)->with(['teachers', 'classrooms']),
            'classrooms' => Classroom::whereIn('id', $this->access->classroomIds($user))->withCount([
                'courses',
                'enrollments' => fn (Builder $q) => $q->whereIn('status', ['Activo', 'Pendiente']),
            ]),
            'enrollments' => $this->access->enrollments($user)->with(['student', 'classroom']),
            'schedules' => Schedule::whereIn('classroom_id', $this->access->classroomIds($user))->with(['course', 'teacher', 'classroom']),
            'communications' => $this->access->communications($user)->with(['author:id,name', 'readers' => fn ($q) => $q->where('users.id', $user->id)]),
            'academic-years' => AcademicYear::query()->withCount('periods'),
            'academic-periods' => AcademicPeriod::query()->with('academicYear'),
            default => abort(404),
        };
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'year' => ['sometimes', 'integer', 'between:2020,2100'], 'grade' => ['sometimes', 'integer', 'between:1,6'], 'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'], 'classroom_id' => ['nullable', 'integer'], 'status' => ['nullable', 'string']]);
        $query = $this->query($request, $resource);
        if (! empty($filters['q']) && in_array($resource, ['students', 'teachers', 'courses', 'communications'])) {
            $column = $resource === 'communications' ? 'subject' : 'name';
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(function (Builder $search) use ($column, $term, $resource) {
                $search->whereRaw('LOWER('.$column.') LIKE ?', [$term]);
                if (in_array($resource, ['students', 'teachers'])) {
                    $search->orWhere('dni', 'like', $term);
                }
            });
        }
        if (! empty($filters['q']) && $resource === 'classrooms') {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(function (Builder $search) use ($term) {
                $search->whereRaw('LOWER(section) LIKE ?', [$term])
                    ->orWhereRaw('CAST(grade AS TEXT) LIKE ?', [$term]);
            });
        }
        if (! empty($filters['q']) && $resource === 'academic-periods') {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$term]);
        }
        if (! empty($filters['year']) && in_array($resource, ['classrooms', 'enrollments'])) {
            $query->where('year', $filters['year']);
        }
        if (! empty($filters['grade']) && $resource === 'classrooms') {
            $query->where('grade', $filters['grade']);
        }
        if (! empty($filters['academic_year_id']) && $resource === 'academic-periods') {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
        if (! empty($filters['classroom_id']) && in_array($resource, ['enrollments', 'schedules'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }
        if (! empty($filters['status']) && in_array($resource, ['students', 'teachers', 'enrollments', 'academic-years', 'academic-periods'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->orderBy('id')->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, string $resource, int $id): JsonResponse
    {
        return response()->json(['data' => $this->query($request, $resource)->findOrFail($id)]);
    }

    public function store(AcademicRequest $request, string $resource): JsonResponse
    {
        return $this->persist($request, $resource);
    }

    public function update(AcademicRequest $request, string $resource, int $id): JsonResponse
    {
        return $this->persist($request, $resource, $id);
    }

    private function persist(AcademicRequest $request, string $resource, ?int $id = null): JsonResponse
    {
        $resource === 'communications' ? $this->access->staff($request->user()) : $this->access->admin($request->user());
        $data = $request->validated();
        try {
            $model = DB::transaction(function () use ($request, $resource, $id, $data) {
                $model = $id ? $this->query($request, $resource)->lockForUpdate()->findOrFail($id) : new (self::MODELS[$resource]);
                if ($resource === 'academic-years' && $id && (int) $model->year !== (int) $data['year']) {
                    $inUse = Classroom::where('year', $model->year)->exists() || Enrollment::where('year', $model->year)->exists();
                    $this->ensure(! $inUse, 'year', 'El año tiene aulas o matrículas asociadas y no se puede cambiar.');
                }
                if ($resource === 'academic-periods') {
                    $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
                    $this->ensure($data['starts_on'] >= $academicYear->starts_on->format('Y-m-d') && $data['ends_on'] <= $academicYear->ends_on->format('Y-m-d'), 'starts_on', 'Las fechas del periodo deben estar dentro del año académico.');
                    $overlap = AcademicPeriod::where('academic_year_id', $academicYear->id)
                        ->where('starts_on', '<=', $data['ends_on'])
                        ->where('ends_on', '>=', $data['starts_on'])
                        ->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->exists();
                    $this->ensure(! $overlap, 'starts_on', 'Las fechas se superponen con otro periodo del mismo año.');
                    if ($id) {
                        $hasGrades = Grade::where('period', $model->number)
                            ->whereHas('enrollment', fn (Builder $q) => $q->where('year', $model->academicYear->year))->exists();
                        $sameCycle = (int) $model->academic_year_id === (int) $data['academic_year_id'] && (int) $model->number === (int) $data['number'];
                        $this->ensure(! $hasGrades || $sameCycle, 'number', 'El periodo tiene calificaciones y no puede cambiar de año o número.');
                    }
                }
                if ($resource === 'enrollments') {
                    $room = Classroom::lockForUpdate()->findOrFail($data['classroom_id']);
                    $this->ensure((int) $room->year === (int) $data['year'], 'classroom_id', 'El aula no pertenece al año seleccionado.');
                    $this->ensure((int) substr($data['enrolled_at'], 0, 4) === (int) $data['year'], 'enrolled_at', 'La fecha debe pertenecer al año académico.');
                    if ($data['status'] !== 'Cancelado') {
                        $this->ensure(Student::findOrFail($data['student_id'])->status === 'Activo', 'student_id', 'El estudiante debe estar activo.');
                        $count = $room->enrollments()->whereIn('status', ['Activo', 'Pendiente'])->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->count();
                        $this->ensure($count < $room->capacity, 'classroom_id', 'El aula ya no tiene vacantes.');
                    }
                    if ($id && ($model->attendances()->exists() || $model->grades()->exists())) {
                        $this->ensure((int) $model->classroom_id === (int) $data['classroom_id'] && (int) $model->student_id === (int) $data['student_id'] && (int) $model->year === (int) $data['year'], 'classroom_id', 'La matrícula tiene historial. No se puede cambiar de estudiante, aula o año.');
                    }
                }
                if ($resource === 'classrooms' && $id) {
                    $this->ensure($data['capacity'] >= $model->enrollments()->whereIn('status', ['Activo', 'Pendiente'])->count(), 'capacity', 'La capacidad no puede ser menor a las matrículas existentes.');
                    if ($model->enrollments()->exists() || $model->courses()->exists()) {
                        $this->ensure((int) $model->year === (int) $data['year'] && (int) $model->grade === (int) $data['grade'] && $model->section === $data['section'], 'year', 'El aula tiene relaciones académicas. Crea otra aula para cambiar año, grado o sección.');
                    }
                }
                if ($resource === 'courses') {
                    $this->ensure(Teacher::whereIn('id', $data['teacher_ids'])->where('status', 'Activo')->exists(), 'teacher_ids', 'Selecciona al menos un docente activo.');
                }
                if ($resource === 'courses' && $id) {
                    $scheduledTeacherIds = Schedule::where('course_id', $id)->pluck('teacher_id')->unique();
                    $this->ensure($scheduledTeacherIds->diff($data['teacher_ids'])->isEmpty(), 'teacher_ids', 'No puedes retirar un docente que tiene bloques de horario para este curso.');
                    $usedRooms = Schedule::where('course_id', $id)->pluck('classroom_id')->merge(Enrollment::whereHas('grades', fn (Builder $q) => $q->where('course_id', $id))->pluck('classroom_id'))->unique();
                    $this->ensure($usedRooms->diff($data['classroom_ids'])->isEmpty(), 'classroom_ids', 'No puedes retirar un aula con notas u horarios.');
                }
                if ($resource === 'schedules') {
                    $room = Classroom::lockForUpdate()->findOrFail($data['classroom_id']);
                    $course = Course::findOrFail($data['course_id']);
                    $teacher = Teacher::whereKey($data['teacher_id'])->lockForUpdate()->firstOrFail();
                    $this->ensure($course->classrooms()->whereKey($room->id)->exists(), 'course_id', 'El curso no está asignado al aula.');
                    $this->ensure($course->teachers()->whereKey($teacher->id)->exists(), 'teacher_id', 'El docente no está asignado a este curso.');
                    $this->ensure($teacher->status === 'Activo', 'teacher_id', 'El docente asignado está inactivo.');
                    $conflict = Schedule::where('day', $data['day'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])
                        ->whereHas('classroom', fn (Builder $q) => $q->where('year', $room->year))
                        ->where(fn (Builder $q) => $q->where('classroom_id', $room->id)->orWhere('teacher_id', $teacher->id))
                        ->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->exists();
                    $this->ensure(! $conflict, 'starts_at', 'El aula o docente ya tiene una clase en ese horario.');
                }
                if ($resource === 'communications') {
                    if ($id) {
                        abort_unless($request->user()->role === 'admin' || $model->author_id === $request->user()->id, 403);
                    }
                    if ($request->user()->role === 'docente') {
                        $this->ensure(! empty($data['classroom_id']), 'classroom_id', 'Selecciona una de tus aulas.');
                        $this->access->classroom($request->user(), (int) $data['classroom_id']);
                        $this->ensure(in_array($data['audience'], ['all', 'padre']), 'audience', 'Un docente puede comunicar a las familias de su aula.');
                    }
                    $data['author_id'] = $id ? $model->author_id : $request->user()->id;
                    $data['published_at'] = $data['draft'] ? null : ($model->published_at ?? now());
                }
                $model->fill(collect($data)->except(['classroom_ids', 'course_ids', 'teacher_ids'])->all())->save();
                if ($resource === 'teachers') {
                    $courseIds = collect($data['course_ids'] ?? [])->map(fn ($courseId) => (int) $courseId)->unique();
                    $scheduledCourseIds = Schedule::where('teacher_id', $model->id)->pluck('course_id')->unique();
                    $this->ensure($scheduledCourseIds->diff($courseIds)->isEmpty(), 'course_ids', 'No puedes retirar un curso que tiene bloques de horario para este docente.');
                    $model->courses()->sync($courseIds);
                }
                if ($resource === 'courses') {
                    $model->classrooms()->sync($data['classroom_ids']);
                    $model->teachers()->sync($data['teacher_ids']);
                }
                if ($resource === 'academic-years' && $model->status === 'Activo') {
                    $this->activateAcademicYear($model);
                }
                if ($resource === 'academic-periods' && $model->status === 'Activo') {
                    $this->activateAcademicPeriod($model);
                }

                return $model;
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23505', '23000'])) {
                throw ValidationException::withMessages(['record' => 'El registro ya existe o tiene una relación no válida.']);
            }
            throw $exception;
        }

        return response()->json(['data' => $this->query($request, $resource)->findOrFail($model->id), 'message' => 'Registro guardado.'], $id ? 200 : 201);
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $this->access->admin($request->user());
        $model = $this->query($request, $resource)->findOrFail($id);
        try {
            DB::transaction(function () use ($model, $resource) {
                if ($resource === 'academic-years') {
                    $settings = DB::table('school_settings')->first();
                    $this->ensure($model->status !== 'Activo' && (int) ($settings->year ?? 0) !== (int) $model->year, 'year', 'No puedes eliminar el año académico activo.');
                    $inUse = Classroom::where('year', $model->year)->exists() || Enrollment::where('year', $model->year)->exists();
                    $this->ensure(! $inUse, 'year', 'El año tiene aulas o matrículas asociadas.');
                }
                if ($resource === 'academic-periods') {
                    $settings = DB::table('school_settings')->first();
                    $isCurrent = (int) ($settings->year ?? 0) === (int) $model->academicYear->year && (int) ($settings->period ?? 0) === (int) $model->number;
                    $this->ensure($model->status !== 'Activo' && ! $isCurrent, 'period', 'No puedes eliminar el periodo académico activo.');
                    $hasGrades = Grade::where('period', $model->number)->whereHas('enrollment', fn (Builder $q) => $q->where('year', $model->academicYear->year))->exists();
                    $this->ensure(! $hasGrades, 'period', 'El periodo tiene calificaciones asociadas.');
                }
                if ($resource === 'teachers') {
                    $this->ensure(! Schedule::where('teacher_id', $model->id)->exists(), 'teacher', 'El docente tiene bloques de horario asociados.');
                    $singleTeacherCourse = $model->courses()->withCount('teachers')->get()->firstWhere('teachers_count', '<=', 1);
                    $this->ensure(! $singleTeacherCourse, 'teacher', 'Asigna otro docente a todos sus cursos antes de eliminarlo.');
                    $model->courses()->detach();
                }
                $model->delete();
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23503', '23000'])) {
                return response()->json(['message' => 'Este registro tiene historial académico asociado. Cambia su estado en lugar de eliminarlo.'], 409);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Registro eliminado.']);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        abort_unless($request->user()?->active, 401);
        $filters = $request->validate(['year' => ['sometimes', 'integer', 'between:2020,2100']]);
        $year = (int) ($filters['year'] ?? 0);
        $settings = DB::table('school_settings')->first();
        $year = $year ?: ($settings->year ?? (int) date('Y'));

        return response()->json([
            'user' => $request->user(),
            'settings' => $settings,
            'year' => $year,
            'years' => AcademicYear::orderByDesc('year')->pluck('year'),
            'periods' => AcademicPeriod::whereHas('academicYear', fn (Builder $q) => $q->where('year', $year))->orderBy('number')->get(),
            'classrooms' => $this->query($request, 'classrooms')->where('year', $year)->orderBy('grade')->orderBy('section')->get(),
            'courses' => $this->query($request, 'courses')->whereHas('classrooms', fn (Builder $q) => $q->where('year', $year))->get(),
            'teachers' => $this->query($request, 'teachers')->get(),
            'dashboard' => $this->summary->dashboard($request->user(), $year, (int) ($settings->period ?? 1)),
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $data = $request->validate(['classroom_id' => ['required', 'integer', 'exists:classrooms,id'], 'date' => ['required', 'date']]);
        $this->access->classroom($request->user(), (int) $data['classroom_id']);

        return response()->json(['data' => $this->access->enrollments($request->user())->where('classroom_id', $data['classroom_id'])->where('status', 'Activo')->with(['student', 'attendances' => fn ($q) => $q->where('date', $data['date'])])->get()]);
    }

    public function saveAttendance(Request $request): JsonResponse
    {
        $this->access->staff($request->user());
        $data = $request->validate(['classroom_id' => ['required', 'integer', 'exists:classrooms,id'], 'date' => ['required', 'date', 'before_or_equal:today'], 'records' => ['required', 'array', 'min:1', 'max:100'], 'records.*.enrollment_id' => ['required', 'integer', 'distinct', 'exists:enrollments,id'], 'records.*.status' => ['required', 'in:P,F,T,J'], 'records.*.notes' => ['nullable', 'string', 'max:500']]);
        $this->access->classroom($request->user(), (int) $data['classroom_id']);
        DB::transaction(function () use ($data, $request) {
            foreach ($data['records'] as $row) {
                $enrollment = Enrollment::lockForUpdate()->findOrFail($row['enrollment_id']);
                $this->ensure($enrollment->status === 'Activo' && (int) $enrollment->classroom_id === (int) $data['classroom_id'], 'records', 'Todos los alumnos deben estar matriculados en el aula seleccionada.');
                $this->ensure((int) substr($data['date'], 0, 4) === (int) $enrollment->year && $data['date'] >= $enrollment->enrolled_at, 'date', 'La fecha debe pertenecer al año académico y ser posterior a la matrícula.');
                Attendance::updateOrCreate(['enrollment_id' => $enrollment->id, 'date' => $data['date']], ['status' => $row['status'], 'notes' => $row['notes'] ?? null, 'recorded_by' => $request->user()->id]);
            }
        });

        return response()->json(['message' => 'Asistencia guardada.', 'count' => count($data['records'])]);
    }

    public function grades(Request $request): JsonResponse
    {
        $data = $request->validate(['classroom_id' => ['required', 'integer', 'exists:classrooms,id'], 'period' => ['required', 'integer', 'between:1,4']]);
        $this->access->classroom($request->user(), (int) $data['classroom_id']);
        $courseIds = $this->access->courses($request->user())->pluck('id');

        return response()->json(['data' => $this->access->enrollments($request->user())->where('classroom_id', $data['classroom_id'])->where('status', 'Activo')->with(['student', 'grades' => fn ($q) => $q->where('period', $data['period'])->whereIn('course_id', $courseIds)])->get()]);
    }

    public function saveGrades(Request $request): JsonResponse
    {
        $this->access->staff($request->user());
        $data = $request->validate(['classroom_id' => ['required', 'integer', 'exists:classrooms,id'], 'course_id' => ['required', 'integer', 'exists:courses,id'], 'period' => ['required', 'integer', 'between:1,4'], 'records' => ['required', 'array', 'min:1', 'max:100'], 'records.*.enrollment_id' => ['required', 'integer', 'distinct', 'exists:enrollments,id'], 'records.*.score' => ['present', 'nullable', 'numeric', 'between:0,20'], 'records.*.notes' => ['nullable', 'string', 'max:500']]);
        $this->access->grade($request->user(), (int) $data['course_id'], (int) $data['classroom_id']);
        $this->ensure(Course::findOrFail($data['course_id'])->classrooms()->whereKey($data['classroom_id'])->exists(), 'course_id', 'El curso no corresponde al aula.');
        DB::transaction(function () use ($data, $request) {
            foreach ($data['records'] as $row) {
                $enrollment = Enrollment::lockForUpdate()->findOrFail($row['enrollment_id']);
                $this->ensure($enrollment->status === 'Activo' && (int) $enrollment->classroom_id === (int) $data['classroom_id'], 'records', 'El estudiante no está matriculado en el aula.');
                if ($row['score'] === null) {
                    Grade::where(['enrollment_id' => $enrollment->id, 'course_id' => $data['course_id'], 'period' => $data['period']])->delete();
                    continue;
                }
                Grade::updateOrCreate(['enrollment_id' => $enrollment->id, 'course_id' => $data['course_id'], 'period' => $data['period']], ['score' => $row['score'], 'notes' => $row['notes'] ?? null, 'recorded_by' => $request->user()->id]);
            }
        });

        return response()->json(['message' => 'Calificaciones guardadas.', 'count' => count($data['records'])]);
    }

    public function receipt(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['archived' => ['sometimes', 'boolean']]);
        $message = $this->access->communications($request->user())->findOrFail($id);
        $message->readers()->syncWithoutDetaching([$request->user()->id => array_merge(['read_at' => now()], $data)]);

        return response()->json(['message' => 'Comunicado actualizado.']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'period' => ['sometimes', 'integer', 'between:1,4']]);

        return response()->json(['data' => $this->summary->dashboard($request->user(), (int) $data['year'], (int) ($data['period'] ?? 1))]);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $this->reportFilters($request);

        return response()->json(['data' => $this->summary->report($request->user(), $data['type'], (int) $data['year'], (int) $data['period'])]);
    }

    private function reportFilters(Request $request): array
    {
        return $request->validate(['type' => ['required', 'in:academic,attendance,students,enrollment,teachers'], 'year' => ['required', 'integer', 'between:2020,2100'], 'period' => ['required', 'integer', 'between:1,4']]);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->reportFilters($request);
        $report = $this->summary->report($request->user(), $data['type'], (int) $data['year'], (int) $data['period']);

        return response()->streamDownload(function () use ($report) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $report['columns'], ';', '"', '');
            foreach ($report['rows'] as $row) {
                $safe = array_map(function ($value) {
                    $value = (string) ($value ?? '');

                    return preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value;
                }, array_values($row));
                fputcsv($stream, $safe, ';', '"', '');
            }
            fclose($stream);
        }, 'reporte-'.$data['type'].'-'.$data['year'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function settings(Request $request): JsonResponse
    {
        $this->access->admin($request->user());
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'year' => ['required', 'integer', 'between:2020,2100'], 'period' => ['required', 'integer', 'between:1,4']]);
        $academicYear = AcademicYear::firstOrCreate(['year' => $data['year']], [
            'starts_on' => $data['year'].'-03-01',
            'ends_on' => $data['year'].'-12-20',
            'status' => 'Planificado',
        ]);
        if ($academicYear->wasRecentlyCreated) {
            $dates = [['03-01', '05-10'], ['05-11', '07-26'], ['08-05', '10-11'], ['10-14', '12-20']];
            foreach ($dates as $index => [$start, $end]) {
                $academicYear->periods()->create([
                    'number' => $index + 1,
                    'name' => 'Bimestre '.($index + 1),
                    'starts_on' => $data['year'].'-'.$start,
                    'ends_on' => $data['year'].'-'.$end,
                    'status' => 'Planificado',
                ]);
            }
        }
        $academicPeriod = $academicYear->periods()->where('number', $data['period'])->firstOrFail();
        DB::transaction(function () use ($data, $academicYear, $academicPeriod) {
            DB::table('school_settings')->updateOrInsert(['id' => 1], array_merge($data, ['updated_at' => now(), 'created_at' => now()]));
            $academicYear->update(['status' => 'Activo']);
            $academicPeriod->update(['status' => 'Activo']);
            $this->activateAcademicPeriod($academicPeriod);
        });

        return response()->json(['data' => $data, 'message' => 'Configuración guardada.']);
    }

    public function users(Request $request): JsonResponse
    {
        $this->access->admin($request->user());

        return response()->json(['data' => User::select('id', 'name', 'email', 'role', 'active')->orderBy('name')->get()]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $this->access->admin($request->user());
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'password' => ['required', 'string', 'min:10', 'max:255'], 'role' => ['required', 'in:admin,docente,padre']]);

        return response()->json(['data' => User::create($data), 'message' => 'Usuario creado.'], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $this->access->admin($request->user());
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $this->ensure($id !== $request->user()->id || $data['active'], 'active', 'No puedes desactivar tu propia cuenta.');
        $user = User::findOrFail($id);
        $user->update($data);

        return response()->json(['data' => $user, 'message' => 'Usuario actualizado.']);
    }

    private function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function activateAcademicYear(AcademicYear $academicYear): void
    {
        AcademicYear::where('status', 'Activo')->where('id', '!=', $academicYear->id)->get()->each(function (AcademicYear $other) use ($academicYear) {
            $other->update(['status' => $other->year < $academicYear->year ? 'Cerrado' : 'Planificado']);
        });
        $period = $academicYear->periods()->where('status', 'Activo')->first() ?? $academicYear->periods()->first();
        DB::table('school_settings')->where('id', 1)->update(['year' => $academicYear->year, 'period' => $period?->number ?? 1, 'updated_at' => now()]);
    }

    private function activateAcademicPeriod(AcademicPeriod $academicPeriod): void
    {
        $academicYear = $academicPeriod->academicYear;
        AcademicPeriod::where('academic_year_id', $academicYear->id)->where('status', 'Activo')->where('id', '!=', $academicPeriod->id)->get()->each(function (AcademicPeriod $other) use ($academicPeriod) {
            $other->update(['status' => $other->number < $academicPeriod->number ? 'Cerrado' : 'Planificado']);
        });
        if ($academicYear->status !== 'Activo') {
            $academicYear->update(['status' => 'Activo']);
        }
        $this->activateAcademicYear($academicYear);
        DB::table('school_settings')->where('id', 1)->update(['period' => $academicPeriod->number, 'updated_at' => now()]);
    }
}
