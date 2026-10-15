<?php

namespace App\Http\Solicitudes\Academico;

class FiltrosInstitucionalesSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'course_id' => ['nullable', 'integer'],
            'classroom_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'academic_period_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
        ];
    }
}
