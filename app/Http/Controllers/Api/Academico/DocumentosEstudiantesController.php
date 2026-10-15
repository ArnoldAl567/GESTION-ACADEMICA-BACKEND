<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Api\Academico\Compartido\ControladorCrudInstitucional;
use App\Http\Solicitudes\Academico\DocumentoEstudianteSolicitud;
use App\Models\StudentDocument;
use Illuminate\Http\JsonResponse;

class DocumentosEstudiantesController extends ControladorCrudInstitucional
{
    protected const RECURSO = 'student-documents';

    protected const MODELO = StudentDocument::class;

    protected const RELACIONES = ['student:id,name,dni'];

    protected const COLUMNA_BUSQUEDA = 'type';

    public function store(DocumentoEstudianteSolicitud $request): JsonResponse
    {
        return $this->guardar($request, $request->validated());
    }

    public function update(DocumentoEstudianteSolicitud $request, int $id): JsonResponse
    {
        return $this->guardar($request, $request->validated(), $id);
    }
}
