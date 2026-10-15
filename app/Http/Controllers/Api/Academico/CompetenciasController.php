<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Api\Academico\Compartido\ControladorCrudInstitucional;
use App\Http\Solicitudes\Academico\CompetenciaSolicitud;
use App\Models\Competency;
use Illuminate\Http\JsonResponse;

class CompetenciasController extends ControladorCrudInstitucional
{
    protected const RECURSO = 'competencies';

    protected const MODELO = Competency::class;

    protected const RELACIONES = ['course:id,name,category'];

    public function store(CompetenciaSolicitud $request): JsonResponse
    {
        return $this->guardar($request, $request->validated());
    }

    public function update(CompetenciaSolicitud $request, int $id): JsonResponse
    {
        return $this->guardar($request, $request->validated(), $id);
    }
}
