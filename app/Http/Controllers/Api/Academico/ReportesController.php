<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Services\AcademicSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportesController extends Controller
{
    public function __construct(private AcademicSummary $resumen) {}

    public function mostrar(Request $request): JsonResponse
    {
        $filtros = $this->validarFiltros($request);

        return response()->json([
            'data' => $this->resumen->report($request->user(), $filtros['type'], (int) $filtros['year'], (int) $filtros['period']),
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $this->validarFiltros($request);
        $reporte = $this->resumen->report($request->user(), $filtros['type'], (int) $filtros['year'], (int) $filtros['period']);

        return response()->streamDownload(function () use ($reporte) {
            $archivo = fopen('php://output', 'w');
            fwrite($archivo, "\xEF\xBB\xBF");
            fputcsv($archivo, $reporte['columns'], ';', '"', '');
            foreach ($reporte['rows'] as $fila) {
                $filaSegura = array_map(function ($valor) {
                    $valor = (string) ($valor ?? '');

                    return preg_match('/^[=+@\-\t\r\n]/', $valor) ? "'".$valor : $valor;
                }, array_values($fila));
                fputcsv($archivo, $filaSegura, ';', '"', '');
            }
            fclose($archivo);
        }, 'reporte-'.$filtros['type'].'-'.$filtros['year'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{type: string, year: int, period: int} */
    private function validarFiltros(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:academic,attendance,students,enrollment,teachers'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'period' => ['required', 'integer', 'between:1,4'],
        ]);
    }
}
