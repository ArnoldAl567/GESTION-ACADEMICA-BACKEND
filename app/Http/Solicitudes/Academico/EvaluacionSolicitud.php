<?php

namespace App\Http\Solicitudes\Academico;

class EvaluacionSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'competency_id' => ['required', 'exists:competencies,id'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:Práctica,Examen,Proyecto,Tarea,Participación'],
            'evaluation_date' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'between:1,100'],
            'weight' => ['required', 'numeric', 'between:0.01,100'],
            'status' => ['required', 'in:Programada,Aplicada,Calificada,Cancelada'],
        ];
    }
}
