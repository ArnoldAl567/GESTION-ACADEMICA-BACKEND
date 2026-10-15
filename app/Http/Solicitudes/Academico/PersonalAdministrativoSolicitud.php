<?php

namespace App\Http\Solicitudes\Academico;

use Illuminate\Validation\Rule;

class PersonalAdministrativoSolicitud extends SolicitudAcademica
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('administrative_staff', 'user_id')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'dni' => ['required', 'digits:8', Rule::unique('administrative_staff', 'dni')->ignore($id)],
            'email' => ['required', 'email', Rule::unique('administrative_staff', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:25'],
            'position' => ['required', 'in:Director,Coordinador,Auxiliar,Secretaría,Administrativo'],
            'status' => ['required', 'in:Activo,Inactivo'],
        ];
    }
}
