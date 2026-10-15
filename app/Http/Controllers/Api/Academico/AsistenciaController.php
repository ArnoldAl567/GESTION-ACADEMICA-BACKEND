<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Services\AcademicAccess;
use App\Servicios\ReglasAcademicas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    public function __construct(
        private AcademicAccess $acceso,
        private ReglasAcademicas $reglas,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'date' => ['required', 'date'],
        ]);
        $this->acceso->classroom($request->user(), (int) $datos['classroom_id']);

        $matriculas = $this->acceso->enrollments($request->user())
            ->where('classroom_id', $datos['classroom_id'])
            ->where('status', 'Activo')
            ->with(['student', 'attendances' => fn ($query) => $query->where('date', $datos['date'])])
            ->get();

        return response()->json(['data' => $matriculas]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->acceso->staff($request->user());
        $datos = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.enrollment_id' => ['required', 'integer', 'distinct', 'exists:enrollments,id'],
            'records.*.status' => ['required', 'in:P,F,T,J'],
            'records.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->acceso->attendance($request->user(), (int) $datos['classroom_id'], $datos['date']);

        DB::transaction(function () use ($datos, $request) {
            foreach ($datos['records'] as $registro) {
                $matricula = Enrollment::lockForUpdate()->findOrFail($registro['enrollment_id']);
                $this->reglas->verificar($matricula->status === 'Activo' && (int) $matricula->classroom_id === (int) $datos['classroom_id'], 'records', 'Todas las alumnas deben estar matriculadas en el aula seleccionada.');
                $this->reglas->verificar((int) substr($datos['date'], 0, 4) === (int) $matricula->year && $datos['date'] >= $matricula->enrolled_at, 'date', 'La fecha debe pertenecer al año académico y ser posterior a la matrícula.');
                Attendance::updateOrCreate(
                    ['enrollment_id' => $matricula->id, 'date' => $datos['date']],
                    ['status' => $registro['status'], 'notes' => $registro['notes'] ?? null, 'recorded_by' => $request->user()->id],
                );
            }
        });

        return response()->json(['message' => 'Asistencia guardada.', 'count' => count($datos['records'])]);
    }
}
