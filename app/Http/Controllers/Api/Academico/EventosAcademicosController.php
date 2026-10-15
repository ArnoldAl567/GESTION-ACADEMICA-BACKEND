<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Api\Academico\Compartido\ControladorCrudInstitucional;
use App\Http\Solicitudes\Academico\EventoAcademicoSolicitud;
use App\Models\AcademicEvent;
use App\Services\AcademicAccess;
use App\Servicios\Academico\ReglasAcademicasServicio;
use Illuminate\Http\JsonResponse;

class EventosAcademicosController extends ControladorCrudInstitucional
{
    protected const RECURSO = 'academic-events';

    protected const MODELO = AcademicEvent::class;

    protected const RELACIONES = ['academicYear:id,year', 'classroom:id,year,grade,section'];

    protected const COLUMNA_BUSQUEDA = 'title';

    public function __construct(AcademicAccess $access, private ReglasAcademicasServicio $reglas)
    {
        parent::__construct($access);
    }

    public function store(EventoAcademicoSolicitud $request): JsonResponse
    {
        $data = $request->validated();
        $this->reglas->validarEvento($data);

        return $this->guardar($request, $data);
    }

    public function update(EventoAcademicoSolicitud $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $this->reglas->validarEvento($data);

        return $this->guardar($request, $data, $id);
    }
}
