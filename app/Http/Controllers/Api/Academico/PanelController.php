<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Services\AcademicSummary;
use App\Services\AcademicPermissions;
use App\Servicios\ConsultasAcademicas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelController extends Controller
{
    public function __construct(
        private ConsultasAcademicas $consultas,
        private AcademicSummary $resumen,
        private AcademicPermissions $permisos,
    ) {}

    public function datosIniciales(Request $request): JsonResponse
    {
        $filtros = $request->validate(['year' => ['sometimes', 'integer', 'between:2020,2100']]);
        $configuracion = DB::table('school_settings')->first();
        if ($configuracion && is_string($configuracion->achievement_rules ?? null)) {
            $configuracion->achievement_rules = json_decode($configuracion->achievement_rules, true);
        }
        $anio = (int) ($filtros['year'] ?? $configuracion->year ?? date('Y'));
        $usuario = $request->user()->load('roleRecord');

        return response()->json([
            'user' => $usuario,
            'permissions' => $this->permisos->forUser($usuario),
            'settings' => $configuracion,
            'year' => $anio,
            'years' => AcademicYear::orderByDesc('year')->pluck('year'),
            'periods' => AcademicPeriod::whereHas('academicYear', fn (Builder $query) => $query->where('year', $anio))->orderBy('number')->get(),
            'classrooms' => $this->consultas->paraUsuario($usuario, 'classrooms')->where('year', $anio)->orderBy('grade')->orderBy('section')->get(),
            'courses' => $this->consultas->paraUsuario($usuario, 'courses')->whereHas('classrooms', fn (Builder $query) => $query->where('year', $anio))->get(),
            // En el panel familiar basta el nombre de la docente de su aula.
            'teachers' => $this->consultas->paraUsuario($usuario, 'teachers')
                ->when($usuario->accessScope() === 'padre', fn (Builder $query) => $query->select('id', 'name'))
                ->get(),
            'dashboard' => $this->resumen->dashboard($usuario, $anio, (int) ($configuracion->period ?? 1)),
        ]);
    }

    public function resumen(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'period' => ['sometimes', 'integer', 'between:1,4'],
        ]);

        return response()->json([
            'data' => $this->resumen->dashboard($request->user(), (int) $datos['year'], (int) ($datos['period'] ?? 1)),
        ]);
    }
}
