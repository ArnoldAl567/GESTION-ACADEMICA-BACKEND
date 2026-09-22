<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('Planificado');
            $table->timestamps();
        });

        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 60);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('Planificado');
            $table->timestamps();
            $table->unique(['academic_year_id', 'number']);
        });

        $settings = DB::table('school_settings')->first();
        $currentYear = (int) ($settings->year ?? date('Y'));
        $currentPeriod = (int) ($settings->period ?? 1);
        $years = DB::table('classrooms')->distinct()->pluck('year')->push($currentYear)->unique()->sort();

        foreach ($years as $yearValue) {
            $year = (int) $yearValue;
            $yearId = DB::table('academic_years')->insertGetId([
                'year' => $year,
                'starts_on' => $year.'-03-01',
                'ends_on' => $year.'-12-20',
                'status' => $year === $currentYear ? 'Activo' : ($year < $currentYear ? 'Cerrado' : 'Planificado'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $dates = [['03-01', '05-10'], ['05-11', '07-26'], ['08-05', '10-11'], ['10-14', '12-20']];
            foreach ($dates as $index => [$start, $end]) {
                $number = $index + 1;
                $status = $year === $currentYear
                    ? ($number === $currentPeriod ? 'Activo' : ($number < $currentPeriod ? 'Cerrado' : 'Planificado'))
                    : ($year < $currentYear ? 'Cerrado' : 'Planificado');
                DB::table('academic_periods')->insert([
                    'academic_year_id' => $yearId,
                    'number' => $number,
                    'name' => 'Bimestre '.$number,
                    'starts_on' => $year.'-'.$start,
                    'ends_on' => $year.'-'.$end,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
        Schema::dropIfExists('academic_years');
    }
};
