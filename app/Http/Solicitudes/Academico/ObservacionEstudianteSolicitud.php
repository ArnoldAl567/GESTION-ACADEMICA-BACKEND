<?php

namespace App\Http\Solicitudes\Academico;

class ObservacionEstudianteSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'exists:enrollments,id'],
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'type' => ['required', 'in:Académica,Conductual,Tutoría,Reconocimiento'],
            'observation' => ['required', 'string', 'max:4000'],
            'observed_on' => ['required', 'date'],
        ];
    }
}
