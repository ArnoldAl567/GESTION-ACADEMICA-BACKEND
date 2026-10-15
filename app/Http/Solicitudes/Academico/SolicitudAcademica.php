<?php

namespace App\Http\Solicitudes\Academico;

use Illuminate\Foundation\Http\FormRequest;

abstract class SolicitudAcademica extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->active;
    }
}
