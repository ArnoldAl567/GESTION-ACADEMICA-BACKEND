<?php

namespace App\Http\Solicitudes\Academico;

class LibretasSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
        ];
    }
}
