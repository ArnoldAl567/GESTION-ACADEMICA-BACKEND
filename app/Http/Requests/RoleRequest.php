<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active === true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('roles', 'code')->ignore($this->route('id'))],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:250'],
            'scope' => ['required', 'in:admin,docente,padre'],
            'icon' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'El código debe comenzar con una letra y usar solo minúsculas, números, guion o guion bajo.',
            'unique' => 'Ya existe un rol con ese código.',
            'required' => 'El campo :attribute es obligatorio.',
        ];
    }
}
