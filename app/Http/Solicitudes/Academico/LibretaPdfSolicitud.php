<?php

namespace App\Http\Solicitudes\Academico;

class LibretaPdfSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
        ];
    }
}
