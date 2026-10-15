<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Api\Academico\Compartido\ControladorCrudInstitucional;
use App\Http\Solicitudes\Academico\LibretaPdfSolicitud;
use App\Http\Solicitudes\Academico\LibretasSolicitud;
use App\Http\Solicitudes\Academico\ObservacionEstudianteSolicitud;
use App\Models\StudentObservation;
use App\Services\AcademicAccess;
use App\Servicios\Academico\ReglasAcademicasServicio;
use App\Servicios\Academico\SeguimientoAcademicoServicio as ServicioSeguimiento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeguimientoAcademicoController extends ControladorCrudInstitucional
{
    protected const RECURSO = 'student-observations';

    protected const MODELO = StudentObservation::class;

    protected const RELACIONES = ['academicPeriod:id,number,name', 'enrollment.student:id,name,color', 'enrollment.classroom:id,year,grade,section'];

    protected const COLUMNA_BUSQUEDA = 'observation';

    protected const ESCRITURA_PERSONAL = true;

    public function __construct(
        AcademicAccess $access,
        private ReglasAcademicasServicio $reglas,
        private ServicioSeguimiento $seguimiento
    ) {
        parent::__construct($access);
    }

    public function store(ObservacionEstudianteSolicitud $request): JsonResponse
    {
        $data = $request->validated();
        $enrollment = $this->reglas->validarObservacion($data);
        $this->access->classroom($request->user(), $enrollment->classroom_id);
        $data['recorded_by'] = $request->user()->id;

        return $this->guardar($request, $data);
    }

    public function update(ObservacionEstudianteSolicitud $request, int $id): JsonResponse
    {
        $existing = StudentObservation::findOrFail($id);
        $this->access->classroom($request->user(), $existing->enrollment->classroom_id);
        $this->reglas->validarObservacion([
            'enrollment_id' => $existing->enrollment_id,
            'academic_period_id' => $existing->academic_period_id,
        ]);
        $data = $request->validated();
        $enrollment = $this->reglas->validarObservacion($data);
        $this->access->classroom($request->user(), $enrollment->classroom_id);
        $data['recorded_by'] = $request->user()->id;

        return $this->guardar($request, $data, $id);
    }

    public function reportCards(LibretasSolicitud $request): JsonResponse
    {
        $data = $request->validated();
        $records = $this->seguimiento->obtenerLibretas(
            $request->user(),
            (int) $data['classroom_id'],
            (int) $data['academic_period_id']
        );

        return response()->json(['data' => $records]);
    }

    public function pdf(LibretaPdfSolicitud $request, int $enrollment): Response
    {
        $record = $this->seguimiento->obtenerLibreta(
            $request->user(),
            $enrollment,
            (int) $request->validated('academic_period_id'),
        );
        $settings = $this->seguimiento->configuracionInstitucion();
        $filename = 'libreta-'.str($record['student']->name)->slug().'-'.$record['academic_period']->name.'.pdf';

        return Pdf::loadView('pdf.libreta-oficial', [
            'libreta' => $record,
            'institucion' => $settings,
            'logo' => public_path('escudo-ie-80404.png'),
        ])->setPaper('a4')->download($filename);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $observation = StudentObservation::findOrFail($id);
        $this->access->classroom($request->user(), $observation->enrollment->classroom_id);
        $this->reglas->validarObservacion([
            'enrollment_id' => $observation->enrollment_id,
            'academic_period_id' => $observation->academic_period_id,
        ]);

        return parent::destroy($request, $id);
    }
}
