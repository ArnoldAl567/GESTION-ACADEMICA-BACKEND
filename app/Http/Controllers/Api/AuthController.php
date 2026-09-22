<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,docente,padre'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'role' => $data['role'], 'active' => true], $data['remember'] ?? false)) {
            throw ValidationException::withMessages(['email' => 'El correo, la contraseña o el perfil no son correctos.']);
        }
        $request->session()->regenerate();

        return response()->json(['user' => $request->user(), 'csrf_token' => csrf_token()]);
    }

    public function defaultAdmin(Request $request): JsonResponse
    {
        abort_unless(config('auth.default_admin_access'), 404);

        $admin = User::query()
            ->where('email', config('auth.default_admin_email'))
            ->where('role', 'admin')
            ->where('active', true)
            ->firstOrFail();

        Auth::guard('web')->login($admin, true);
        $request->session()->regenerate();

        return response()->json(['user' => $admin, 'csrf_token' => csrf_token()]);
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user()?->active, 401);

        return response()->json(['user' => $request->user(), 'csrf_token' => csrf_token()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.', 'csrf_token' => csrf_token()]);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'min:10', 'max:255']]);
        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return response()->json(['message' => 'Contraseña actualizada.', 'csrf_token' => csrf_token()]);
    }
}
