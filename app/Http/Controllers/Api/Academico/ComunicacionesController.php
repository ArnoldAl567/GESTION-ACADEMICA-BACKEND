<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Services\AcademicAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComunicacionesController extends Controller
{
    public function __construct(private AcademicAccess $acceso) {}

    public function actualizarRecepcion(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['archived' => ['sometimes', 'boolean']]);
        $comunicado = $this->acceso->communications($request->user())->findOrFail($id);
        $comunicado->readers()->syncWithoutDetaching([
            $request->user()->id => array_merge(['read_at' => now()], $datos),
        ]);

        return response()->json(['message' => 'Comunicado actualizado.']);
    }
}
