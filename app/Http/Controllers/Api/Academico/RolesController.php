<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\AcademicAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RolesController extends Controller
{
    public function __construct(private AcademicAccess $access) {}

    public function index(Request $request): JsonResponse
    {
        $this->access->admin($request->user());
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:Activo,Inactivo'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = Role::query()->withCount('users');
        if (! empty($filters['q'])) {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(fn (Builder $roles) => $roles->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(code) LIKE ?', [$term]));
        }
        if (! empty($filters['status'])) {
            $query->where('active', $filters['status'] === 'Activo');
        }

        return response()->json($query->orderByDesc('system')->orderBy('id')->paginate($filters['per_page'] ?? 20));
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $this->access->admin($request->user());
        $role = Role::create(array_merge($request->validated(), ['system' => false]));

        return response()->json(['data' => $role->loadCount('users'), 'message' => 'Rol creado.'], 201);
    }

    public function update(RoleRequest $request, int $id): JsonResponse
    {
        $this->access->admin($request->user());
        $role = Role::withCount('users')->findOrFail($id);
        $data = $request->validated();
        if ($role->code !== $data['code'] && ($role->system || $role->users_count > 0)) {
            throw ValidationException::withMessages(['code' => 'No puedes cambiar el código de un rol base o asignado a usuarios.']);
        }
        if ($role->system && $role->scope !== $data['scope']) {
            throw ValidationException::withMessages(['scope' => 'El nivel de acceso de un rol base no se puede cambiar.']);
        }
        if (! $data['active'] && $request->user()->role === $role->code) {
            throw ValidationException::withMessages(['active' => 'No puedes desactivar el rol de tu propia cuenta.']);
        }
        $role->update($data);

        return response()->json(['data' => $role->fresh()->loadCount('users'), 'message' => 'Rol actualizado.']);
    }

    public function activation(Request $request, int $id): JsonResponse
    {
        $this->access->admin($request->user());
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $role = Role::findOrFail($id);
        if (! $data['active'] && $request->user()->role === $role->code) {
            throw ValidationException::withMessages(['active' => 'No puedes desactivar el rol de tu propia cuenta.']);
        }
        $role->update($data);

        return response()->json(['data' => $role->loadCount('users'), 'message' => 'Estado del rol actualizado.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->access->admin($request->user());
        $role = Role::withCount('users')->findOrFail($id);
        if ($role->system) {
            throw ValidationException::withMessages(['role' => 'Los roles base del sistema no se pueden eliminar.']);
        }
        if ($role->users_count > 0) {
            throw ValidationException::withMessages(['role' => 'El rol está asignado a usuarios. Reasigna sus cuentas antes de eliminarlo.']);
        }
        $role->delete();

        return response()->json(['message' => 'Rol eliminado.']);
    }
}
