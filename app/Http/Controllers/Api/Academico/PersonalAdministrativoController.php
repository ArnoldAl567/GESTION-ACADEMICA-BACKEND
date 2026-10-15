<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Api\Academico\Compartido\ControladorCrudInstitucional;
use App\Http\Solicitudes\Academico\PersonalAdministrativoSolicitud;
use App\Models\AdministrativeStaff;
use Illuminate\Http\JsonResponse;

class PersonalAdministrativoController extends ControladorCrudInstitucional
{
    protected const RECURSO = 'administrative-staff';

    protected const MODELO = AdministrativeStaff::class;

    protected const RELACIONES = ['user:id,name,email'];

    protected const LECTURA_SOLO_ADMIN = true;

    public function store(PersonalAdministrativoSolicitud $request): JsonResponse
    {
        return $this->guardar($request, $request->validated());
    }

    public function update(PersonalAdministrativoSolicitud $request, int $id): JsonResponse
    {
        return $this->guardar($request, $request->validated(), $id);
    }
}
