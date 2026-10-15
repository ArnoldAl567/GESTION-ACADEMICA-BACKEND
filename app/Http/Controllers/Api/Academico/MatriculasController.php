<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\AcademicAccess;
use App\Servicios\ReglasAcademicas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatriculasController extends Controller
{
    public function __construct(
        private AcademicAccess $acceso,
        private ReglasAcademicas $reglas,
    ) {}

    public function candidatos(Request $request): JsonResponse
    {
        $this->acceso->admin($request->user());
        $datos = $request->validate(['year' => ['required', 'integer', 'between:2020,2100']]);

        $estudiantes = Student::query()
            ->where('status', 'Activo')
            ->whereDoesntHave('enrollments', fn ($matriculas) => $matriculas->where('year', $datos['year']))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $estudiantes]);
    }

    public function actualizarEstado(Request $request, int $id): JsonResponse
    {
        $this->acceso->admin($request->user());
        $datos = $request->validate(['active' => ['required', 'boolean']]);

        $matricula = DB::transaction(function () use ($id, $datos) {
            $matricula = Enrollment::with(['student', 'classroom'])->lockForUpdate()->findOrFail($id);

            if ($datos['active']) {
                $aula = Classroom::lockForUpdate()->findOrFail($matricula->classroom_id);
                $this->reglas->verificar($matricula->student->status === 'Activo', 'active', 'El estudiante debe estar activo para activar su matrícula.');
                $ocupadas = $aula->enrollments()
                    ->whereIn('status', ['Activo', 'Pendiente'])
                    ->where('id', '!=', $matricula->id)
                    ->count();
                $this->reglas->verificar($ocupadas < $aula->capacity, 'active', 'La sección ya no tiene vacantes disponibles.');
                $matricula->update(['status' => 'Activo']);
            } else {
                $matricula->update(['status' => 'Pendiente']);
            }

            return $matricula->refresh()->load(['student', 'classroom']);
        }, 3);

        return response()->json([
            'data' => $matricula,
            'message' => $datos['active'] ? 'Matrícula activada.' : 'Matrícula marcada como pendiente.',
        ]);
    }
}
