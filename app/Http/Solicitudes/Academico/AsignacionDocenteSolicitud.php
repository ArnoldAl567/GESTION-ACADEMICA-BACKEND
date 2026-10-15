<?php

namespace App\Http\Solicitudes\Academico;

class AsignacionDocenteSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'exists:teachers,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'status' => ['required', 'in:Activo,Inactivo'],
        ];
    }
}
