<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Api\Academico\Compartido\ControladorCrudInstitucional;
use App\Http\Solicitudes\Academico\AsignacionDocenteSolicitud;
use App\Models\TeacherAssignment;
use App\Services\AcademicAccess;
use App\Servicios\Academico\AsignacionAulaServicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsignacionesDocentesController extends ControladorCrudInstitucional
{
    protected const RECURSO = 'teacher-assignments';

    protected const MODELO = TeacherAssignment::class;

    protected const RELACIONES = ['teacher:id,name', 'classroom:id,year,grade,section'];

    public function __construct(AcademicAccess $access, private AsignacionAulaServicio $asignaciones)
    {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $this->access->staff($request->user());
        $filtros = $request->validate([
            'year' => ['sometimes', 'integer', 'between:2020,2100'],
            'status' => ['sometimes', 'in:Activo,Inactivo'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $representantes = TeacherAssignment::query()->selectRaw('MIN(id)')
            ->groupBy('teacher_id', 'classroom_id');
        $consulta = TeacherAssignment::query()->with(self::RELACIONES)
            ->whereIn('id', $representantes)
            ->when(isset($filtros['year']), fn (Builder $q) => $q->whereHas('classroom', fn (Builder $aula) => $aula->where('year', $filtros['year'])))
            ->when(isset($filtros['status']), fn (Builder $q) => $q->where('status', $filtros['status']));

        if ($request->user()->accessScope() === 'docente') {
            $consulta->whereHas('teacher', fn (Builder $q) => $q->where('user_id', $request->user()->id));
        }

        return response()->json($consulta->orderBy('id')->paginate($filtros['per_page'] ?? 20));
    }

    public function store(AsignacionDocenteSolicitud $request): JsonResponse
    {
        $this->access->admin($request->user());
        $asignacion = $this->asignaciones->guardar($request->validated());

        return response()->json(['data' => $asignacion, 'message' => 'Aula asignada a la docente para todos sus cursos y periodos.'], 201);
    }

    public function update(AsignacionDocenteSolicitud $request, int $id): JsonResponse
    {
        $this->access->admin($request->user());
        $asignacion = $this->asignaciones->guardar($request->validated(), $id);

        return response()->json(['data' => $asignacion, 'message' => 'Asignación del aula actualizada.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->access->admin($request->user());
        $this->asignaciones->eliminar($id);

        return response()->json(['message' => 'Asignación del aula eliminada.']);
    }
}
