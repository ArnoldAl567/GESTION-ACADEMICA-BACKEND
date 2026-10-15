<?php

namespace App\Http\Solicitudes\Academico;

use App\Models\Evaluation;

class CalificacionesEvaluacionSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        /** @var Evaluation $evaluation */
        $evaluation = $this->route('evaluation');

        return [
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.enrollment_id' => ['required', 'integer', 'distinct', 'exists:enrollments,id'],
            'records.*.score' => ['present', 'nullable', 'numeric', 'min:0', 'max:'.$evaluation->max_score],
            'records.*.achievement_level' => ['nullable', 'string', 'max:30'],
            'records.*.observation' => ['nullable', 'string', 'max:500'],
        ];
    }
}
