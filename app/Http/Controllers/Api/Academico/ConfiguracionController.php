<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\User;
use App\Services\AcademicAccess;
use App\Servicios\ReglasAcademicas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConfiguracionController extends Controller
{
    public function __construct(
        private AcademicAccess $acceso,
        private ReglasAcademicas $reglas,
    ) {}

    public function guardar(Request $request): JsonResponse
    {
        $this->acceso->admin($request->user());
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'period' => ['required', 'integer', 'between:1,4'],
            'achievement_rules' => ['sometimes', 'array', 'size:4'],
            'achievement_rules.*.level' => ['required', Rule::in(['AD', 'A', 'B', 'C']), 'distinct'],
            'achievement_rules.*.min' => ['required', 'numeric', 'between:0,20'],
            'achievement_rules.*.label' => ['required', 'string', 'max:80'],
            'achievement_rules.*.conclusion' => ['required', 'string', 'max:500'],
            'director_name' => ['nullable', 'string', 'max:150'],
            'director_title' => ['sometimes', 'string', 'max:100'],
            'tutor_signature_label' => ['sometimes', 'string', 'max:100'],
            'report_card_footer' => ['nullable', 'string', 'max:500'],
        ]);
        if (isset($datos['achievement_rules'])) {
            $minimums = collect($datos['achievement_rules'])->keyBy('level')->map(fn ($rule) => (float) $rule['min']);
            $this->reglas->verificar(
                $minimums->has(['AD', 'A', 'B', 'C'])
                    && $minimums['AD'] > $minimums['A']
                    && $minimums['A'] > $minimums['B']
                    && $minimums['B'] > $minimums['C']
                    && $minimums['C'] === 0.0,
                'achievement_rules',
                'Los niveles deben descender en el orden AD, A, B y C; el nivel C debe iniciar en 0.'
            );
        }
        $anioAcademico = AcademicYear::firstOrCreate(['year' => $datos['year']], [
            'starts_on' => $datos['year'].'-03-01',
            'ends_on' => $datos['year'].'-12-20',
            'status' => 'Planificado',
        ]);

        if ($anioAcademico->wasRecentlyCreated) {
            $this->crearPeriodosIniciales($anioAcademico);
        }

        $periodoAcademico = $anioAcademico->periods()->where('number', $datos['period'])->firstOrFail();
        DB::transaction(function () use ($datos, $periodoAcademico) {
            $datosPersistencia = $datos;
            if (isset($datos['achievement_rules'])) {
                $datosPersistencia['achievement_rules'] = json_encode($datos['achievement_rules'], JSON_UNESCAPED_UNICODE);
            }
            DB::table('school_settings')->updateOrInsert(
                ['id' => 1],
                array_merge($datosPersistencia, ['updated_at' => now(), 'created_at' => now()]),
            );
            $this->reglas->activarPeriodo($periodoAcademico);
        });

        return response()->json(['data' => $datos, 'message' => 'Configuración guardada.']);
    }

    public function listarUsuarios(Request $request): JsonResponse
    {
        $this->acceso->admin($request->user());

        return response()->json([
            'data' => User::select('id', 'name', 'email', 'role', 'active')->with('roleRecord:id,code,name,scope,active')->orderBy('name')->get(),
        ]);
    }

    public function crearUsuario(Request $request): JsonResponse
    {
        $this->acceso->admin($request->user());
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:10', 'max:255'],
            'role' => ['required', Rule::exists('roles', 'code')->where('active', true)],
        ]);

        return response()->json(['data' => User::create($datos), 'message' => 'Usuario creado.'], 201);
    }

    public function actualizarUsuario(Request $request, int $id): JsonResponse
    {
        $this->acceso->admin($request->user());
        $datos = $request->validate(['active' => ['required', 'boolean']]);
        $this->reglas->verificar($id !== $request->user()->id || $datos['active'], 'active', 'No puedes desactivar tu propia cuenta.');
        $usuario = User::findOrFail($id);
        $usuario->update($datos);

        return response()->json(['data' => $usuario, 'message' => 'Usuario actualizado.']);
    }

    private function crearPeriodosIniciales(AcademicYear $anioAcademico): void
    {
        $fechas = [['03-01', '05-10'], ['05-11', '07-26'], ['08-05', '10-11'], ['10-14', '12-20']];
        foreach ($fechas as $indice => [$inicio, $fin]) {
            $anioAcademico->periods()->create([
                'number' => $indice + 1,
                'name' => 'Bimestre '.($indice + 1),
                'starts_on' => $anioAcademico->year.'-'.$inicio,
                'ends_on' => $anioAcademico->year.'-'.$fin,
                'status' => 'Planificado',
            ]);
        }
    }
}
