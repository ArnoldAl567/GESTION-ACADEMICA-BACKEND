<?php

namespace App\Http\Controllers\Api\Academico\Compartido;

use App\Http\Controllers\Controller;
use App\Http\Solicitudes\Academico\FiltrosInstitucionalesSolicitud;
use App\Services\AcademicAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ControladorCrudInstitucional extends Controller
{
    protected const RECURSO = '';

    protected const MODELO = Model::class;

    protected const RELACIONES = [];

    protected const COLUMNA_BUSQUEDA = 'name';

    protected const LECTURA_SOLO_ADMIN = false;

    protected const ESCRITURA_PERSONAL = false;

    public function __construct(protected AcademicAccess $access) {}

    public function index(FiltrosInstitucionalesSolicitud $request): JsonResponse
    {
        $this->autorizarLectura($request);
        $filters = $request->validated();
        $query = (static::MODELO)::query()->with(static::RELACIONES);
        $this->limitarAlUsuario($query, $request);

        if (! empty($filters['q'])) {
            $query->whereRaw('LOWER('.static::COLUMNA_BUSQUEDA.') LIKE ?', ['%'.mb_strtolower($filters['q']).'%']);
        }
        foreach (['course_id', 'classroom_id', 'academic_period_id', 'academic_year_id', 'status'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['student_id'])) {
            static::RECURSO === 'student-documents'
                ? $query->where('student_id', $filters['student_id'])
                : $query->whereHas('enrollment', fn (Builder $enrollment) => $enrollment->where('student_id', $filters['student_id']));
        }

        return response()->json($query->latest('id')->paginate($filters['per_page'] ?? 20));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizarEscritura($request);
        (static::MODELO)::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Registro eliminado.']);
    }

    protected function guardar(Request $request, array $data, ?int $id = null): JsonResponse
    {
        $this->autorizarEscritura($request);
        $model = $id ? (static::MODELO)::query()->findOrFail($id) : new (static::MODELO);
        $model->fill($data)->save();
        $model->load(static::RELACIONES);

        return response()->json(['data' => $model, 'message' => 'Registro guardado.'], $id ? 200 : 201);
    }

    protected function autorizarEscritura(Request $request): void
    {
        static::ESCRITURA_PERSONAL
            ? $this->access->staff($request->user())
            : $this->access->admin($request->user());
    }

    private function autorizarLectura(Request $request): void
    {
        static::LECTURA_SOLO_ADMIN
            ? $this->access->admin($request->user())
            : abort_unless($request->user()?->active, 401);
    }

    private function limitarAlUsuario(Builder $query, Request $request): void
    {
        $user = $request->user();
        if ($user->accessScope() === 'admin') {
            return;
        }

        $classrooms = $this->access->classroomIds($user);
        if (in_array(static::RECURSO, ['teacher-assignments', 'academic-events', 'evaluations'])) {
            $query->where(fn (Builder $scope) => $scope
                ->whereIn('classroom_id', $classrooms)
                ->when(static::RECURSO === 'academic-events', fn (Builder $event) => $event->orWhereNull('classroom_id')));
            if (static::RECURSO === 'academic-events') {
                $query->where('status', '!=', 'Cancelado');
            }
            if (static::RECURSO === 'evaluations' && $user->accessScope() === 'padre') {
                $query->where('status', 'Calificada')
                    ->whereHas('academicPeriod', fn (Builder $period) => $period->where('status', 'Cerrado'))
                    ->with(['scores' => fn ($scores) => $scores
                        ->whereHas('enrollment.student', fn (Builder $students) => $students->where('guardian_user_id', $user->id))]);
            }
        } elseif (static::RECURSO === 'competencies') {
            $query->whereIn('course_id', $this->access->courses($user)->select('courses.id'));
            if ($user->accessScope() === 'padre') {
                $query->where('status', 'Activo');
            }
        } elseif (static::RECURSO === 'student-documents') {
            $query->whereIn('student_id', $this->access->students($user)->select('students.id'));
        } elseif (static::RECURSO === 'student-observations') {
            $query->whereHas('enrollment', fn (Builder $enrollment) => $enrollment
                ->whereIn('student_id', $this->access->students($user)->select('students.id')));
        }
    }
}
