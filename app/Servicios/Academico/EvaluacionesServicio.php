<?php

namespace App\Servicios\Academico;

use App\Models\AcademicPeriod;
use App\Models\Competency;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluacionesServicio
{
    public function __construct(private ConfiguracionEvaluacionServicio $configuracion) {}

    public function validarEvaluacion(array $data, ?int $evaluationId = null): void
    {
        $period = AcademicPeriod::findOrFail($data['academic_period_id']);
        $this->verificarPeriodoAbierto($period);

        $competency = Competency::findOrFail($data['competency_id']);
        $enrollmentGrade = DB::table('classrooms')->where('id', $data['classroom_id'])->value('grade');
        if ((int) $competency->course_id !== (int) $data['course_id'] || ($competency->grade !== null && (int) $competency->grade !== (int) $enrollmentGrade)) {
            throw ValidationException::withMessages([
                'competency_id' => 'La competencia no corresponde al curso y grado seleccionados.',
            ]);
        }

        if ($data['status'] === 'Cancelada') {
            return;
        }

        $usedWeight = Evaluation::query()
            ->where('academic_period_id', $data['academic_period_id'])
            ->where('classroom_id', $data['classroom_id'])
            ->where('course_id', $data['course_id'])
            ->where('status', '!=', 'Cancelada')
            ->when($evaluationId, fn ($query) => $query->where('id', '!=', $evaluationId))
            ->sum('weight');

        if ((float) $usedWeight + (float) $data['weight'] > 100.0001) {
            $available = max(0, 100 - (float) $usedWeight);
            throw ValidationException::withMessages([
                'weight' => "El peso total del curso no puede superar el 100%. Queda disponible {$available}%.",
            ]);
        }
    }

    public function verificarPeriodoAbierto(AcademicPeriod|int $period): AcademicPeriod
    {
        $period = is_int($period) ? AcademicPeriod::findOrFail($period) : $period;
        if ($period->status === 'Cerrado') {
            throw ValidationException::withMessages([
                'academic_period_id' => 'El periodo está cerrado y ya no admite modificaciones.',
            ]);
        }

        return $period;
    }

    public function guardarCalificaciones(Evaluation $evaluation, array $records, User $user): void
    {
        $this->verificarPeriodoAbierto((int) $evaluation->academic_period_id);

        DB::transaction(function () use ($evaluation, $records, $user): void {
            foreach ($records as $record) {
                $enrollment = Enrollment::findOrFail($record['enrollment_id']);
                abort_unless(
                    $enrollment->status === 'Activo' && $enrollment->classroom_id === $evaluation->classroom_id,
                    422,
                    'La matrícula no corresponde a la evaluación.'
                );
                $score = $record['score'];
                $normalizedScore = $score === null ? null : round((float) $score * 20 / (float) $evaluation->max_score, 2);
                EvaluationScore::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'enrollment_id' => $enrollment->id],
                    [
                        'score' => $score,
                        'achievement_level' => $normalizedScore === null ? null : $this->configuracion->nivel($normalizedScore),
                        'observation' => $record['observation'] ?? null,
                        'recorded_by' => $user->id,
                    ]
                );
            }

            $evaluation->update(['status' => 'Calificada']);
        });
    }
}
