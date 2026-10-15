<?php

namespace App\Servicios\Academico;

use App\Models\AcademicPeriod;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\AcademicAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeguimientoAcademicoServicio
{
    public function __construct(
        private AcademicAccess $access,
        private ConfiguracionEvaluacionServicio $configuracion,
    ) {}

    public function obtenerLibretas(User $user, int $classroomId, int $periodId): Collection
    {
        $this->access->classroom($user, $classroomId);
        $period = AcademicPeriod::with('academicYear')->findOrFail($periodId);
        abort_unless((int) $period->academicYear->year === (int) \App\Models\Classroom::whereKey($classroomId)->value('year'), 404);
        if ($user->accessScope() === 'padre' && $period->status !== 'Cerrado') {
            return collect();
        }
        $evaluations = Evaluation::query()
            ->where('classroom_id', $classroomId)
            ->where('academic_period_id', $periodId)
            ->where('status', '!=', 'Cancelada')
            ->when($user->accessScope() === 'padre', fn ($query) => $query->where('status', 'Calificada'))
            ->with(['course:id,name', 'competency:id,course_id,name'])
            ->get();

        return $this->access->enrollments($user)
            ->where('classroom_id', $classroomId)
            ->where('status', 'Activo')
            ->with([
                'student:id,name,dni,color',
                'classroom:id,year,grade,section',
                'attendances' => fn ($query) => $query->whereBetween('date', [$period->starts_on, $period->ends_on]),
                'grades' => fn ($query) => $query->where('period', $period->number)->with('course:id,name'),
                'evaluationScores' => fn ($query) => $query
                    ->whereHas('evaluation', fn ($evaluation) => $evaluation
                        ->where('academic_period_id', $periodId)
                        ->where('status', '!=', 'Cancelada')
                        ->when($user->accessScope() === 'padre', fn ($query) => $query->where('status', 'Calificada')))
                    ->with(['evaluation.course:id,name', 'evaluation.competency:id,course_id,name']),
                'observations' => fn ($query) => $query->where('academic_period_id', $periodId)->latest('observed_on'),
            ])
            ->get()
            ->map(fn (Enrollment $enrollment) => $this->convertirLibreta($enrollment, $period, $evaluations));
    }

    public function obtenerLibreta(User $user, int $enrollmentId, int $periodId): array
    {
        $enrollment = $this->access->enrollments($user)->whereKey($enrollmentId)->firstOrFail();
        $record = $this->obtenerLibretas($user, (int) $enrollment->classroom_id, $periodId)
            ->firstWhere('enrollment_id', $enrollmentId);

        abort_if($record === null, 404);

        return $record;
    }

    public function configuracionInstitucion(): object
    {
        return DB::table('school_settings')->where('id', 1)->first() ?? (object) [];
    }

    private function convertirLibreta(Enrollment $enrollment, AcademicPeriod $period, Collection $evaluations): array
    {
        $courseAverages = $this->promediosPorCurso($enrollment, $evaluations);
        $competencyAverages = $this->promediosPorCompetencia($enrollment);
        $averages = collect($courseAverages)->pluck('average')->filter(fn ($value) => $value !== null);
        $average = $averages->isNotEmpty() ? round((float) $averages->avg(), 2) : null;
        $rule = $average === null ? null : $this->configuracion->reglaPara($average);
        $attendance = $this->resumenAsistencia($enrollment);
        $academicObservation = $enrollment->observations->first(fn ($item) => in_array($item->type, ['Académica', 'Tutoría', 'Reconocimiento']));
        $conductObservation = $enrollment->observations->firstWhere('type', 'Conductual');

        return [
            'enrollment_id' => $enrollment->id,
            'student' => $enrollment->student,
            'classroom' => $enrollment->classroom,
            'academic_period' => $period,
            'period_closed' => $period->status === 'Cerrado',
            'average' => $average,
            'achievement_level' => $rule['level'] ?? null,
            'achievement_label' => $rule['label'] ?? null,
            'descriptive_conclusion' => $academicObservation?->observation ?? ($rule['conclusion'] ?? 'Aún no hay evidencias suficientes para emitir una conclusión descriptiva.'),
            'conduct' => $conductObservation?->observation ?? 'Sin observaciones de conducta registradas.',
            'attendance' => $attendance['percentage'],
            'attendance_detail' => $attendance,
            'course_averages' => $courseAverages,
            'competency_averages' => $competencyAverages,
            'grades' => $enrollment->grades,
            'evaluation_scores' => $enrollment->evaluationScores,
            'observations' => $enrollment->observations,
        ];
    }

    private function promediosPorCurso(Enrollment $enrollment, Collection $evaluations): array
    {
        $scoresByEvaluation = $enrollment->evaluationScores->keyBy('evaluation_id');
        $evaluationCourses = $evaluations->groupBy('course_id')->map(function (Collection $courseEvaluations) use ($scoresByEvaluation) {
            $scored = $courseEvaluations->filter(fn ($evaluation) => $scoresByEvaluation->get($evaluation->id)?->score !== null);
            $usedWeight = (float) $scored->sum('weight');
            $weighted = $scored->sum(function ($evaluation) use ($scoresByEvaluation) {
                $score = (float) $scoresByEvaluation->get($evaluation->id)->score;
                $normalized = $score * 20 / (float) $evaluation->max_score;

                return $normalized * (float) $evaluation->weight;
            });
            $average = $usedWeight > 0 ? round($weighted / $usedWeight, 2) : null;
            $rule = $average === null ? null : $this->configuracion->reglaPara($average);

            return [
                'course_id' => $courseEvaluations->first()->course_id,
                'course' => $courseEvaluations->first()->course?->name,
                'average' => $average,
                'achievement_level' => $rule['level'] ?? null,
                'achievement_label' => $rule['label'] ?? null,
                'weight_total' => round((float) $courseEvaluations->sum('weight'), 2),
                'weight_evaluated' => round($usedWeight, 2),
                'evaluations_count' => $courseEvaluations->count(),
            ];
        });

        $legacyCourses = $enrollment->grades->mapWithKeys(function ($grade) {
            $average = round((float) $grade->score, 2);
            $rule = $this->configuracion->reglaPara($average);

            return [$grade->course_id => [
                'course_id' => $grade->course_id,
                'course' => $grade->course?->name,
                'average' => $average,
                'achievement_level' => $rule['level'],
                'achievement_label' => $rule['label'],
                'weight_total' => 100,
                'weight_evaluated' => 100,
                'evaluations_count' => 0,
            ]];
        });

        return $evaluationCourses->union($legacyCourses)->values()->all();
    }

    private function promediosPorCompetencia(Enrollment $enrollment): array
    {
        return $enrollment->evaluationScores
            ->filter(fn ($score) => $score->score !== null && $score->evaluation?->competency_id)
            ->groupBy(fn ($score) => $score->evaluation->competency_id)
            ->map(function (Collection $scores) {
                $usedWeight = (float) $scores->sum(fn ($score) => $score->evaluation->weight);
                $weighted = $scores->sum(function ($score) {
                    $normalized = (float) $score->score * 20 / (float) $score->evaluation->max_score;

                    return $normalized * (float) $score->evaluation->weight;
                });
                $average = $usedWeight > 0 ? round($weighted / $usedWeight, 2) : null;
                $rule = $average === null ? null : $this->configuracion->reglaPara($average);

                return [
                    'competency_id' => $scores->first()->evaluation->competency_id,
                    'competency' => $scores->first()->evaluation->competency?->name,
                    'course' => $scores->first()->evaluation->course?->name,
                    'average' => $average,
                    'achievement_level' => $rule['level'] ?? null,
                    'achievement_label' => $rule['label'] ?? null,
                ];
            })->values()->all();
    }

    /** @return array{percentage:?float,present:int,absent:int,late:int,justified:int,total:int} */
    private function resumenAsistencia(Enrollment $enrollment): array
    {
        $total = $enrollment->attendances->count();
        $present = $enrollment->attendances->where('status', 'P')->count();
        $late = $enrollment->attendances->where('status', 'T')->count();

        return [
            'percentage' => $total ? round(($present + $late) * 100 / $total, 1) : null,
            'present' => $present,
            'absent' => $enrollment->attendances->where('status', 'F')->count(),
            'late' => $late,
            'justified' => $enrollment->attendances->where('status', 'J')->count(),
            'total' => $total,
        ];
    }
}
