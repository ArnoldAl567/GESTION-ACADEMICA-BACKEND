<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerfilApoderadoController extends Controller
{
    public function mostrar(Request $request): JsonResponse
    {
        $apoderado = Guardian::where('user_id', $request->user()->id)
            ->withCount('students')
            ->firstOrFail();

        return response()->json(['data' => $apoderado]);
    }

    public function actualizar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:250'],
        ]);

        $apoderado = DB::transaction(function () use ($request, $datos) {
            $apoderado = Guardian::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $apoderado->update($datos);
            $apoderado->students()->update(['phone' => $apoderado->phone]);

            return $apoderado->fresh();
        });

        return response()->json(['data' => $apoderado, 'message' => 'Datos de contacto actualizados.']);
    }
}
