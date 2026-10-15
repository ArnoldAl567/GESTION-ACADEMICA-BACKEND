<?php

namespace App\Http\Solicitudes\Academico;

class DocumentoEstudianteSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'type' => ['required', 'string', 'max:80'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'status' => ['required', 'in:Vigente,Pendiente,Vencido'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
