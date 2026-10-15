<?php

namespace App\Servicios;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReglasAcademicas
{
    public function verificar(bool $condicion, string $campo, string $mensaje): void
    {
        if (! $condicion) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }

    public function activarAnio(AcademicYear $anioAcademico): void
    {
        AcademicYear::where('status', 'Activo')
            ->where('id', '!=', $anioAcademico->id)
            ->lockForUpdate()
            ->get()
            ->each(function (AcademicYear $otroAnio) use ($anioAcademico) {
                $otroAnio->update(['status' => $otroAnio->year < $anioAcademico->year ? 'Cerrado' : 'Planificado']);
            });

        AcademicPeriod::where('status', 'Activo')
            ->where('academic_year_id', '!=', $anioAcademico->id)
            ->lockForUpdate()
            ->get()
            ->each(function (AcademicPeriod $otroPeriodo) use ($anioAcademico) {
                $estado = $otroPeriodo->ends_on->lt($anioAcademico->starts_on) ? 'Cerrado' : 'Planificado';
                $otroPeriodo->update(['status' => $estado]);
            });

        if ($anioAcademico->status !== 'Activo') {
            $anioAcademico->update(['status' => 'Activo']);
        }

        $periodo = $anioAcademico->periods()->where('status', 'Activo')->first() ?? $anioAcademico->periods()->first();
        DB::table('school_settings')->where('id', 1)->update([
            'year' => $anioAcademico->year,
            'period' => $periodo?->number ?? 1,
            'updated_at' => now(),
        ]);
    }

    public function activarPeriodo(AcademicPeriod $periodoAcademico): void
    {
        $anioAcademico = $periodoAcademico->academicYear;
        AcademicPeriod::where('status', 'Activo')
            ->where('id', '!=', $periodoAcademico->id)
            ->lockForUpdate()
            ->get()
            ->each(function (AcademicPeriod $otroPeriodo) use ($periodoAcademico) {
                $estado = $otroPeriodo->ends_on->lt($periodoAcademico->starts_on) ? 'Cerrado' : 'Planificado';
                $otroPeriodo->update(['status' => $estado]);
            });

        if ($periodoAcademico->status !== 'Activo') {
            $periodoAcademico->update(['status' => 'Activo']);
        }

        $this->activarAnio($anioAcademico);
        DB::table('school_settings')->where('id', 1)->update([
            'period' => $periodoAcademico->number,
            'updated_at' => now(),
        ]);
    }

    public function desactivarAnio(AcademicYear $anioAcademico): void
    {
        if ($anioAcademico->status !== 'Activo') {
            return;
        }

        $anioAcademico->periods()
            ->where('status', 'Activo')
            ->lockForUpdate()
            ->get()
            ->each(fn (AcademicPeriod $periodo) => $periodo->update(['status' => $this->estadoInactivo($periodo->ends_on)]));

        $anioAcademico->update(['status' => $this->estadoInactivo($anioAcademico->ends_on)]);
    }

    public function desactivarPeriodo(AcademicPeriod $periodoAcademico): void
    {
        if ($periodoAcademico->status === 'Activo') {
            $periodoAcademico->update(['status' => $this->estadoInactivo($periodoAcademico->ends_on)]);
        }
    }

    private function estadoInactivo(mixed $fechaFin): string
    {
        return $fechaFin->lt(today()) ? 'Cerrado' : 'Planificado';
    }
}
