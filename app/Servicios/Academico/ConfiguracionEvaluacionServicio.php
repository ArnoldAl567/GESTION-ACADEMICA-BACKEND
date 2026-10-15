<?php

namespace App\Servicios\Academico;

use Illuminate\Support\Facades\DB;

class ConfiguracionEvaluacionServicio
{
    /** @return array<int, array{level:string,min:float,label:string,conclusion:string}> */
    public function reglas(): array
    {
        $settings = DB::table('school_settings')->where('id', 1)->first();
        $stored = $settings?->achievement_rules;
        $rules = is_string($stored) ? json_decode($stored, true) : $stored;

        if (! is_array($rules) || count($rules) !== 4) {
            return $this->reglasPredeterminadas();
        }

        return collect($rules)
            ->map(fn (array $rule) => [
                'level' => (string) ($rule['level'] ?? 'C'),
                'min' => (float) ($rule['min'] ?? 0),
                'label' => (string) ($rule['label'] ?? ''),
                'conclusion' => (string) ($rule['conclusion'] ?? ''),
            ])
            ->sortByDesc('min')
            ->values()
            ->all();
    }

    public function nivel(float $score): string
    {
        return $this->reglaPara($score)['level'];
    }

    /** @return array{level:string,min:float,label:string,conclusion:string} */
    public function reglaPara(float $score): array
    {
        $score = max(0, min(20, $score));

        return collect($this->reglas())->first(fn (array $rule) => $score >= $rule['min'])
            ?? collect($this->reglas())->last();
    }

    /** @return array<int, array{level:string,min:float,label:string,conclusion:string}> */
    public function reglasPredeterminadas(): array
    {
        return [
            ['level' => 'AD', 'min' => 18, 'label' => 'Logro destacado', 'conclusion' => 'Supera satisfactoriamente los aprendizajes previstos.'],
            ['level' => 'A', 'min' => 14, 'label' => 'Logro esperado', 'conclusion' => 'Alcanza los aprendizajes previstos para el periodo.'],
            ['level' => 'B', 'min' => 11, 'label' => 'En proceso', 'conclusion' => 'Está próximo a alcanzar los aprendizajes previstos y requiere acompañamiento.'],
            ['level' => 'C', 'min' => 0, 'label' => 'En inicio', 'conclusion' => 'Requiere mayor tiempo y acompañamiento para desarrollar los aprendizajes previstos.'],
        ];
    }
}
