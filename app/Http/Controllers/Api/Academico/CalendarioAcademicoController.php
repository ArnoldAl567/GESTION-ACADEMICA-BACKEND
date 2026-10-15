<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Services\AcademicAccess;
use App\Servicios\ReglasAcademicas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarioAcademicoController extends Controller
{
    public function __construct(
        private AcademicAccess $acceso,
        private ReglasAcademicas $reglas,
    ) {}

    public function actualizarEstadoAnio(Request $request, int $id): JsonResponse
    {
        $this->acceso->admin($request->user());
        $datos = $request->validate(['active' => ['required', 'boolean']]);

        $anio = DB::transaction(function () use ($id, $datos) {
            $anio = AcademicYear::lockForUpdate()->findOrFail($id);

            $datos['active']
                ? $this->reglas->activarAnio($anio)
                : $this->reglas->desactivarAnio($anio);

            return $anio->refresh()->loadCount('periods');
        }, 3);

        return response()->json([
            'data' => $anio,
            'message' => $datos['active'] ? 'Año académico activado.' : 'Año académico desactivado.',
        ]);
    }

    public function actualizarEstadoPeriodo(Request $request, int $id): JsonResponse
    {
        $this->acceso->admin($request->user());
        $datos = $request->validate(['active' => ['required', 'boolean']]);

        $periodo = DB::transaction(function () use ($id, $datos) {
            $periodo = AcademicPeriod::with('academicYear')->lockForUpdate()->findOrFail($id);

            $datos['active']
                ? $this->reglas->activarPeriodo($periodo)
                : $this->reglas->desactivarPeriodo($periodo);

            return $periodo->refresh()->load('academicYear');
        }, 3);

        return response()->json([
            'data' => $periodo,
            'message' => $datos['active'] ? 'Periodo académico activado.' : 'Periodo académico desactivado.',
        ]);
    }
}
