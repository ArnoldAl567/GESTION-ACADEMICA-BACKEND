<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\AcademicAccess;
use App\Servicios\ReglasAcademicas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalificacionesController extends Controller
{
    public function __construct(
        private AcademicAccess $acceso,
        private ReglasAcademicas $reglas,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'period' => ['required', 'integer', 'between:1,4'],
        ]);
        $this->acceso->classroom($request->user(), (int) $datos['classroom_id']);
        $cursosPermitidos = $this->acceso->courses($request->user())->pluck('id');
        $anio = DB::table('classrooms')->where('id', $datos['classroom_id'])->value('year');
        $periodoCerrado = AcademicPeriod::where('number', $datos['period'])
            ->whereHas('academicYear', fn (Builder $query) => $query->where('year', $anio))
            ->where('status', 'Cerrado')->exists();
        $mostrarNotas = $request->user()->accessScope() !== 'padre' || $periodoCerrado;
        $matriculas = $this->acceso->enrollments($request->user())
            ->where('classroom_id', $datos['classroom_id'])
            ->where('status', 'Activo')
            ->with(['student', 'grades' => fn ($query) => $query->where('period', $datos['period'])->whereIn('course_id', $cursosPermitidos)
                ->when(! $mostrarNotas, fn ($grades) => $grades->whereRaw('1 = 0'))])
            ->get();

        return response()->json(['data' => $matriculas]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->acceso->staff($request->user());
        $datos = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'period' => ['required', 'integer', 'between:1,4'],
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.enrollment_id' => ['required', 'integer', 'distinct', 'exists:enrollments,id'],
            'records.*.score' => ['present', 'nullable', 'numeric', 'between:0,20'],
            'records.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->reglas->verificar(Course::findOrFail($datos['course_id'])->classrooms()->whereKey($datos['classroom_id'])->exists(), 'course_id', 'El curso no corresponde al aula.');
        $periodo = AcademicPeriod::query()
            ->whereHas('academicYear', fn ($query) => $query->where('year', DB::table('classrooms')->where('id', $datos['classroom_id'])->value('year')))
            ->where('number', $datos['period'])
            ->firstOrFail();
        $this->acceso->grade($request->user(), (int) $datos['course_id'], (int) $datos['classroom_id'], $periodo->id);
        $this->reglas->verificar($periodo->status !== 'Cerrado', 'period', 'El periodo está cerrado y ya no admite modificaciones.');

        DB::transaction(function () use ($datos, $request) {
            foreach ($datos['records'] as $registro) {
                $matricula = Enrollment::lockForUpdate()->findOrFail($registro['enrollment_id']);
                $this->reglas->verificar($matricula->status === 'Activo' && (int) $matricula->classroom_id === (int) $datos['classroom_id'], 'records', 'El estudiante no está matriculado en el aula.');
                $identidad = ['enrollment_id' => $matricula->id, 'course_id' => $datos['course_id'], 'period' => $datos['period']];
                if ($registro['score'] === null) {
                    Grade::where($identidad)->delete();

                    continue;
                }
                Grade::updateOrCreate($identidad, ['score' => $registro['score'], 'notes' => $registro['notes'] ?? null, 'recorded_by' => $request->user()->id]);
            }
        });

        return response()->json(['message' => 'Calificaciones guardadas.', 'count' => count($datos['records'])]);
    }
}
