<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
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
use App\Services\AcademicAccess;
use App\Servicios\Academico\AsignacionAulaServicio;
use App\Servicios\ConsultasAcademicas;
use App\Servicios\ReglasAcademicas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicController extends Controller
{
    private const MODELS = ['students' => Student::class, 'guardians' => Guardian::class, 'teachers' => Teacher::class, 'courses' => Course::class, 'classrooms' => Classroom::class, 'enrollments' => Enrollment::class, 'schedules' => Schedule::class, 'communications' => Communication::class, 'academic-years' => AcademicYear::class, 'academic-periods' => AcademicPeriod::class];

    public function __construct(
        private AcademicAccess $access,
        private ConsultasAcademicas $consultas,
        private ReglasAcademicas $reglas,
        private AsignacionAulaServicio $asignaciones,
    ) {}

    public function index(Request $request, string $resource): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'year' => ['sometimes', 'integer', 'between:2020,2100'], 'grade' => ['sometimes', 'integer', 'between:1,6'], 'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'], 'classroom_id' => ['nullable', 'integer'], 'status' => ['nullable', 'string']]);
        $query = $this->consultas->paraUsuario($request->user(), $resource);
        if (! empty($filters['q']) && in_array($resource, ['students', 'guardians', 'teachers', 'courses', 'communications'])) {
            $column = $resource === 'communications' ? 'subject' : 'name';
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(function (Builder $search) use ($column, $term, $resource) {
                $search->whereRaw('LOWER('.$column.') LIKE ?', [$term]);
                if (in_array($resource, ['students', 'guardians', 'teachers'])) {
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
        if (! empty($filters['grade']) && $resource === 'enrollments') {
            $query->whereHas('classroom', fn (Builder $classrooms) => $classrooms->where('grade', $filters['grade']));
        }
        if ($resource === 'students' && (! empty($filters['year']) || ! empty($filters['grade']) || ! empty($filters['classroom_id']))) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($filters) {
                $enrollments
                    ->when(! empty($filters['year']), fn (Builder $items) => $items->where('year', $filters['year']))
                    ->when(! empty($filters['classroom_id']), fn (Builder $items) => $items->where('classroom_id', $filters['classroom_id']))
                    ->when(! empty($filters['grade']), fn (Builder $items) => $items->whereHas('classroom', fn (Builder $classrooms) => $classrooms->where('grade', $filters['grade'])));
            });
        }
        if (! empty($filters['academic_year_id']) && $resource === 'academic-periods') {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }
        if (! empty($filters['classroom_id']) && in_array($resource, ['enrollments', 'schedules'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }
        if (! empty($filters['status']) && in_array($resource, ['students', 'guardians', 'teachers', 'enrollments', 'academic-years', 'academic-periods'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->orderBy('id')->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, string $resource, int $id): JsonResponse
    {
        return response()->json(['data' => $this->consultas->paraUsuario($request->user(), $resource)->findOrFail($id)]);
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
        in_array($resource, ['communications', 'schedules'], true)
            ? $this->access->staff($request->user())
            : $this->access->admin($request->user());
        $data = $request->validated();
        try {
            $model = DB::transaction(function () use ($request, $resource, $id, $data) {
                $model = $id ? $this->consultas->paraUsuario($request->user(), $resource)->lockForUpdate()->findOrFail($id) : new (self::MODELS[$resource]);
                if ($resource === 'academic-years' && $id && (int) $model->year !== (int) $data['year']) {
                    $inUse = Classroom::where('year', $model->year)->exists() || Enrollment::where('year', $model->year)->exists();
                    $this->reglas->verificar(! $inUse, 'year', 'El año tiene aulas o matrículas asociadas y no se puede cambiar.');
                }
                if ($resource === 'academic-periods') {
                    $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
                    $maxPeriods = $academicYear->period_type === 'Trimestral' ? 3 : 4;
                    $this->reglas->verificar((int) $data['number'] <= $maxPeriods, 'number', 'El año '.$academicYear->period_type.' solo admite '.$maxPeriods.' periodos.');
                    $this->reglas->verificar($data['starts_on'] >= $academicYear->starts_on->format('Y-m-d') && $data['ends_on'] <= $academicYear->ends_on->format('Y-m-d'), 'starts_on', 'Las fechas del periodo deben estar dentro del año académico.');
                    $overlap = AcademicPeriod::where('academic_year_id', $academicYear->id)
                        ->where('starts_on', '<=', $data['ends_on'])
                        ->where('ends_on', '>=', $data['starts_on'])
                        ->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->exists();
                    $this->reglas->verificar(! $overlap, 'starts_on', 'Las fechas se superponen con otro periodo del mismo año.');
                    if ($id) {
                        $hasAssignments = TeacherAssignment::where('academic_period_id', $id)->exists();
                        $this->reglas->verificar(! $hasAssignments || (int) $model->academic_year_id === (int) $data['academic_year_id'], 'academic_year_id', 'El periodo tiene docentes asignadas y no puede cambiar de año.');
                        $hasGrades = Grade::where('period', $model->number)
                            ->whereHas('enrollment', fn (Builder $q) => $q->where('year', $model->academicYear->year))->exists();
                        $sameCycle = (int) $model->academic_year_id === (int) $data['academic_year_id'] && (int) $model->number === (int) $data['number'];
                        $this->reglas->verificar(! $hasGrades || $sameCycle, 'number', 'El periodo tiene calificaciones y no puede cambiar de año o número.');
                    }
                }
                if ($resource === 'enrollments') {
                    $room = Classroom::lockForUpdate()->findOrFail($data['classroom_id']);
                    $this->reglas->verificar((int) $room->year === (int) $data['year'], 'classroom_id', 'El aula no pertenece al año seleccionado.');
                    $this->reglas->verificar((int) substr($data['enrolled_at'], 0, 4) === (int) $data['year'], 'enrolled_at', 'La fecha debe pertenecer al año académico.');
                    if ($data['status'] !== 'Cancelado') {
                        $this->reglas->verificar(Student::findOrFail($data['student_id'])->status === 'Activo', 'student_id', 'El estudiante debe estar activo.');
                        $count = $room->enrollments()->whereIn('status', ['Activo', 'Pendiente'])->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->count();
                        $this->reglas->verificar($count < $room->capacity, 'classroom_id', 'El aula ya no tiene vacantes.');
                    }
                    if ($id && ($model->attendances()->exists() || $model->grades()->exists())) {
                        $this->reglas->verificar((int) $model->classroom_id === (int) $data['classroom_id'] && (int) $model->student_id === (int) $data['student_id'] && (int) $model->year === (int) $data['year'], 'classroom_id', 'La matrícula tiene historial. No se puede cambiar de estudiante, aula o año.');
                    }
                }
                if ($resource === 'classrooms' && $id) {
                    $this->reglas->verificar($data['capacity'] >= $model->enrollments()->whereIn('status', ['Activo', 'Pendiente'])->count(), 'capacity', 'La capacidad no puede ser menor a las matrículas existentes.');
                    if ($model->enrollments()->exists() || $model->courses()->exists()) {
                        $this->reglas->verificar((int) $model->year === (int) $data['year'] && (int) $model->grade === (int) $data['grade'] && $model->section === $data['section'], 'year', 'El aula tiene relaciones académicas. Crea otra aula para cambiar año, grado o sección.');
                    }
                }
                if ($resource === 'courses' && $id) {
                    $usedRooms = Schedule::where('course_id', $id)->pluck('classroom_id')->merge(Enrollment::whereHas('grades', fn (Builder $q) => $q->where('course_id', $id))->pluck('classroom_id'))->unique();
                    $this->reglas->verificar($usedRooms->diff($data['classroom_ids'])->isEmpty(), 'classroom_ids', 'No puedes retirar un aula con notas u horarios.');
                }
                if ($resource === 'teachers' && $id && $data['status'] !== 'Activo') {
                    $this->reglas->verificar(! TeacherAssignment::where('teacher_id', $id)->where('status', 'Activo')->exists(), 'status', 'Desactiva primero la asignación activa de esta docente.');
                }
                if ($resource === 'schedules') {
                    if ($id) {
                        $this->access->schedule($request->user(), (int) $model->classroom_id);
                    }
                    $this->access->schedule($request->user(), (int) $data['classroom_id']);
                    $room = Classroom::lockForUpdate()->findOrFail($data['classroom_id']);
                    $course = Course::findOrFail($data['course_id']);
                    $assignment = TeacherAssignment::where('classroom_id', $room->id)->where('status', 'Activo')->first();
                    $this->reglas->verificar($assignment !== null, 'classroom_id', 'Asigna una docente titular a esta aula antes de crear el horario.');
                    $teacher = Teacher::whereKey($assignment->teacher_id)->lockForUpdate()->firstOrFail();
                    $this->reglas->verificar($course->classrooms()->whereKey($room->id)->exists(), 'course_id', 'El curso no está asignado al aula.');
                    $this->reglas->verificar(! isset($data['teacher_id']) || (int) $data['teacher_id'] === $teacher->id, 'teacher_id', 'El horario debe corresponder a la docente titular del aula.');
                    $this->reglas->verificar($teacher->status === 'Activo', 'teacher_id', 'El docente asignado está inactivo.');
                    $data['teacher_id'] = $teacher->id;
                    $conflict = Schedule::where('day', $data['day'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])
                        ->whereHas('classroom', fn (Builder $q) => $q->where('year', $room->year))
                        ->where(fn (Builder $q) => $q->where('classroom_id', $room->id)->orWhere('teacher_id', $teacher->id))
                        ->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->exists();
                    $this->reglas->verificar(! $conflict, 'starts_at', 'El aula o docente ya tiene una clase en ese horario.');
                }
                if ($resource === 'communications') {
                    if ($id) {
                        abort_unless($request->user()->accessScope() === 'admin' || $model->author_id === $request->user()->id, 403);
                    }
                    if ($request->user()->accessScope() === 'docente') {
                        $this->reglas->verificar(! empty($data['classroom_id']), 'classroom_id', 'Selecciona una de tus aulas.');
                        $this->access->classroom($request->user(), (int) $data['classroom_id']);
                        $this->reglas->verificar(in_array($data['audience'], ['all', 'padre']), 'audience', 'Un docente puede comunicar a las familias de su aula.');
                    }
                    $data['author_id'] = $id ? $model->author_id : $request->user()->id;
                    $data['published_at'] = $data['draft'] ? null : ($model->published_at ?? now());
                }
                $activarDespuesDeGuardar = in_array($resource, ['academic-years', 'academic-periods']) && $data['status'] === 'Activo';
                if ($activarDespuesDeGuardar) {
                    $data['status'] = 'Planificado';
                }
                $model->fill(collect($data)->except(['classroom_ids'])->all())->save();
                if ($resource === 'guardians') {
                    $model->students()->update([
                        'guardian' => $model->name,
                        'phone' => $model->phone,
                        'guardian_user_id' => $model->user_id,
                    ]);
                }
                if ($resource === 'courses') {
                    $model->classrooms()->sync($data['classroom_ids']);
                    TeacherAssignment::where('course_id', $model->id)
                        ->whereNotIn('classroom_id', $data['classroom_ids'])->delete();
                    $this->asignaciones->sincronizarCurso($model->load('classrooms'));
                }
                if ($resource === 'academic-periods') {
                    $this->asignaciones->sincronizarPeriodo($model->load('academicYear'));
                }
                if ($resource === 'academic-years' && $activarDespuesDeGuardar) {
                    $this->reglas->activarAnio($model);
                }
                if ($resource === 'academic-periods' && $activarDespuesDeGuardar) {
                    $this->reglas->activarPeriodo($model);
                }

                return $model;
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23505', '23000'])) {
                throw ValidationException::withMessages(['record' => 'El registro ya existe o tiene una relación no válida.']);
            }
            throw $exception;
        }

        return response()->json(['data' => $this->consultas->paraUsuario($request->user(), $resource)->findOrFail($model->id), 'message' => 'Registro guardado.'], $id ? 200 : 201);
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $resource === 'schedules' ? $this->access->staff($request->user()) : $this->access->admin($request->user());
        $model = $this->consultas->paraUsuario($request->user(), $resource)->findOrFail($id);
        if ($resource === 'schedules') {
            $this->access->schedule($request->user(), (int) $model->classroom_id);
        }
        try {
            DB::transaction(function () use ($model, $resource) {
                if ($resource === 'academic-years') {
                    $settings = DB::table('school_settings')->first();
                    $this->reglas->verificar($model->status !== 'Activo' && (int) ($settings->year ?? 0) !== (int) $model->year, 'year', 'No puedes eliminar el año académico activo.');
                    $inUse = Classroom::where('year', $model->year)->exists() || Enrollment::where('year', $model->year)->exists();
                    $this->reglas->verificar(! $inUse, 'year', 'El año tiene aulas o matrículas asociadas.');
                }
                if ($resource === 'academic-periods') {
                    $settings = DB::table('school_settings')->first();
                    $isCurrent = (int) ($settings->year ?? 0) === (int) $model->academicYear->year && (int) ($settings->period ?? 0) === (int) $model->number;
                    $this->reglas->verificar($model->status !== 'Activo' && ! $isCurrent, 'period', 'No puedes eliminar el periodo académico activo.');
                    $hasGrades = Grade::where('period', $model->number)->whereHas('enrollment', fn (Builder $q) => $q->where('year', $model->academicYear->year))->exists();
                    $this->reglas->verificar(! $hasGrades, 'period', 'El periodo tiene calificaciones asociadas.');
                }
                if ($resource === 'teachers') {
                    $this->reglas->verificar(! Schedule::where('teacher_id', $model->id)->exists(), 'teacher', 'El docente tiene bloques de horario asociados.');
                    $this->reglas->verificar(! TeacherAssignment::where('teacher_id', $model->id)->exists(), 'teacher', 'La docente tiene aulas asignadas. Elimina primero esas asignaciones.');
                    $model->courses()->detach();
                }
                if ($resource === 'courses') {
                    $this->reglas->verificar(! Schedule::where('course_id', $model->id)->exists() && ! Grade::where('course_id', $model->id)->exists(), 'course', 'El curso tiene horarios o notas y no puede eliminarse.');
                    TeacherAssignment::where('course_id', $model->id)->delete();
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
}
