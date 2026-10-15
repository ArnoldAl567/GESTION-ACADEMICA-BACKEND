<?php

namespace App\Http\Requests;

use App\Models\Guardian;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('resource') === 'students' && ! $this->route('id') && ! $this->exists('sex')) {
            $this->merge(['sex' => 'F']);
        }
        if ($this->route('resource') !== 'students' || ! $this->filled('guardian_id')) {
            return;
        }

        $guardian = Guardian::find($this->integer('guardian_id'));
        if ($guardian) {
            $this->merge([
                'guardian' => $guardian->name,
                'phone' => $guardian->phone,
                'guardian_user_id' => $guardian->user_id,
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->active === true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $resource = $this->route('resource');
        $unique = fn (string $table, string $column) => Rule::unique($table, $column)->ignore($id);
        $color = ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $guardianRoleCodes = Role::where('scope', 'padre')->where('active', true)->pluck('code');
        $teacherRoleCodes = Role::where('scope', 'docente')->where('active', true)->pluck('code');

        return match ($resource) {
            'students' => [
                'name' => ['required', 'string', 'max:150'],
                'sex' => $id ? ['sometimes', 'in:F'] : ['required', 'in:F'],
                'dni' => ['required', 'digits:8', $unique('students', 'dni')],
                'birth_date' => ['nullable', 'date', 'before:today'],
                'guardian' => ['required', 'string', 'max:150'],
                'phone' => ['required', 'string', 'max:25'],
                'guardian_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', $guardianRoleCodes))],
                'guardian_id' => ['nullable', 'exists:guardians,id'],
                'status' => ['required', 'in:Activo,Inactivo,Trasladado'],
                'color' => $color,
            ],
            'guardians' => [
                'name' => ['required', 'string', 'max:150'],
                'dni' => ['required', 'digits:8', $unique('guardians', 'dni')],
                'phone' => ['required', 'string', 'max:25'],
                'email' => ['required', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:250'],
                'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', $guardianRoleCodes)), $unique('guardians', 'user_id')],
                'status' => ['required', 'in:Activo,Inactivo'],
            ],
            'teachers' => [
                'name' => ['required', 'string', 'max:150'],
                'dni' => ['required', 'digits:8', $unique('teachers', 'dni')],
                'email' => ['required', 'email', 'max:255', $unique('teachers', 'email')],
                'phone' => ['nullable', 'string', 'max:25'],
                'status' => ['required', 'in:Activo,Inactivo'],
                'color' => $color,
                'user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', $teacherRoleCodes)), $unique('teachers', 'user_id')],
            ],
            'courses' => [
                'name' => ['required', 'string', 'max:150', $unique('courses', 'name')],
                'category' => ['required', 'string', 'max:80'],
                'description' => ['nullable', 'string', 'max:2000'],
                'hours' => ['required', 'integer', 'between:1,40'],
                'color' => $color,
                'soft' => $color,
                'classroom_ids' => ['required', 'array', 'min:1'],
                'classroom_ids.*' => ['integer', 'distinct', 'exists:classrooms,id'],
            ],
            'classrooms' => [
                'year' => ['required', 'integer', 'between:2020,2100'],
                'grade' => ['required', 'integer', 'between:1,6'],
                'section' => ['required', 'regex:/^[A-Z]{1,3}$/', Rule::unique('classrooms')->where(fn ($q) => $q->where('year', $this->input('year'))->where('grade', $this->input('grade')))->ignore($id)],
                'capacity' => ['required', 'integer', 'between:1,100'],
            ],
            'academic-years' => [
                'year' => ['required', 'integer', 'between:2020,2100', $unique('academic_years', 'year')],
                'starts_on' => ['required', 'date'],
                'ends_on' => ['required', 'date', 'after:starts_on'],
                'period_type' => ['sometimes', 'in:Bimestral,Trimestral'],
                'status' => ['required', 'in:Planificado,Activo,Cerrado'],
            ],
            'academic-periods' => [
                'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
                'number' => ['required', 'integer', 'between:1,4', Rule::unique('academic_periods', 'number')->where('academic_year_id', $this->input('academic_year_id'))->ignore($id)],
                'name' => ['required', 'string', 'max:60'],
                'starts_on' => ['required', 'date'],
                'ends_on' => ['required', 'date', 'after:starts_on'],
                'status' => ['required', 'in:Planificado,Activo,Cerrado'],
            ],
            'enrollments' => [
                'student_id' => ['required', 'exists:students,id', Rule::unique('enrollments')->where('year', $this->input('year'))->ignore($id)],
                'classroom_id' => ['required', 'exists:classrooms,id'],
                'year' => ['required', 'integer', 'between:2020,2100'],
                'enrolled_at' => ['required', 'date'],
                'status' => ['required', 'in:Activo,Pendiente,Cancelado'],
            ],
            'schedules' => [
                'classroom_id' => ['required', 'exists:classrooms,id'],
                'course_id' => ['required', 'exists:courses,id'],
                'teacher_id' => ['sometimes', 'nullable', Rule::exists('teachers', 'id')->where('status', 'Activo')],
                'day' => ['required', 'integer', 'between:1,5'],
                'starts_at' => ['required', 'date_format:H:i'],
                'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            ],
            'communications' => [
                'type' => ['required', 'in:Comunicado,Circular,Urgente'],
                'subject' => ['required', 'string', 'max:200'],
                'body' => ['required', 'string', 'max:20000'],
                'audience' => ['required', 'in:all,admin,docente,padre'],
                'classroom_id' => ['nullable', 'exists:classrooms,id'],
                'draft' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'unique' => 'Ya existe un registro con ese :attribute.', 'exists' => 'El valor de :attribute no existe.', 'digits' => ':attribute debe tener :digits dígitos.'];
    }
}
