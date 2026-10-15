<?php

namespace App\Http\Solicitudes\Academico;

class EventoAcademicoSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:Evaluación,Reunión,Feriado,Actividad escolar,Fecha importante'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'in:Programado,Realizado,Cancelado'],
        ];
    }
}
