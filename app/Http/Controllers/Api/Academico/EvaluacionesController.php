<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Api\Academico\Compartido\ControladorCrudInstitucional;
use App\Http\Solicitudes\Academico\CalificacionesEvaluacionSolicitud;
use App\Http\Solicitudes\Academico\EvaluacionSolicitud;
use App\Models\Evaluation;
use App\Services\AcademicAccess;
use App\Servicios\Academico\EvaluacionesServicio;
use App\Servicios\Academico\ReglasAcademicasServicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluacionesController extends ControladorCrudInstitucional
{
    protected const RECURSO = 'evaluations';

    protected const MODELO = Evaluation::class;

    protected const RELACIONES = ['academicPeriod:id,academic_year_id,number,name,status', 'classroom:id,year,grade,section', 'course:id,name', 'competency:id,course_id,name', 'scores.enrollment.student:id,name,color'];

    protected const COLUMNA_BUSQUEDA = 'title';

    protected const ESCRITURA_PERSONAL = true;

    public function __construct(
        AcademicAccess $access,
        private ReglasAcademicasServicio $reglas,
        private EvaluacionesServicio $evaluaciones
    ) {
        parent::__construct($access);
    }

    public function store(EvaluacionSolicitud $request): JsonResponse
    {
        $data = $request->validated();
        $this->access->grade($request->user(), (int) $data['course_id'], (int) $data['classroom_id'], (int) $data['academic_period_id']);
        $this->reglas->validarCursoAulaPeriodo($data);
        $this->evaluaciones->validarEvaluacion($data);

        return $this->guardar($request, $data);
    }

    public function update(EvaluacionSolicitud $request, int $id): JsonResponse
    {
        $existing = Evaluation::findOrFail($id);
        $this->evaluaciones->verificarPeriodoAbierto((int) $existing->academic_period_id);
        $this->access->grade($request->user(), $existing->course_id, $existing->classroom_id, $existing->academic_period_id);
        $data = $request->validated();
        $this->access->grade($request->user(), (int) $data['course_id'], (int) $data['classroom_id'], (int) $data['academic_period_id']);
        $this->reglas->validarCursoAulaPeriodo($data);
        $this->evaluaciones->validarEvaluacion($data, $id);

        return $this->guardar($request, $data, $id);
    }

    public function scores(CalificacionesEvaluacionSolicitud $request, Evaluation $evaluation): JsonResponse
    {
        $this->access->grade($request->user(), $evaluation->course_id, $evaluation->classroom_id, $evaluation->academic_period_id);
        $this->evaluaciones->guardarCalificaciones($evaluation, $request->validated('records'), $request->user());

        return response()->json(['message' => 'Calificaciones de la evaluación guardadas.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $evaluation = Evaluation::findOrFail($id);
        $this->evaluaciones->verificarPeriodoAbierto((int) $evaluation->academic_period_id);

        return parent::destroy($request, $id);
    }
}
