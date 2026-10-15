<?php

namespace App\Http\Solicitudes\Academico;

class CompetenciaSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'exists:courses,id'],
            'grade' => ['nullable', 'integer', 'between:1,6'],
            'name' => ['required', 'string', 'max:250'],
            'capacity' => ['required', 'string', 'max:2000'],
            'performance' => ['required', 'string', 'max:2000'],
            'evaluation_criterion' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'in:Activo,Inactivo'],
        ];
    }
}
