<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreignId('competency_id')
                ->nullable()
                ->after('course_id')
                ->constrained('competencies')
                ->nullOnDelete();
        });

        Schema::table('school_settings', function (Blueprint $table) {
            $table->json('achievement_rules')->nullable()->after('period');
            $table->string('director_name', 150)->nullable()->after('achievement_rules');
            $table->string('director_title', 100)->default('Director(a)')->after('director_name');
            $table->string('tutor_signature_label', 100)->default('Docente tutor(a)')->after('director_title');
            $table->string('report_card_footer', 500)->nullable()->after('tutor_signature_label');
        });

        DB::table('school_settings')->whereNull('achievement_rules')->update([
            'achievement_rules' => json_encode([
                ['level' => 'AD', 'min' => 18, 'label' => 'Logro destacado', 'conclusion' => 'Supera satisfactoriamente los aprendizajes previstos.'],
                ['level' => 'A', 'min' => 14, 'label' => 'Logro esperado', 'conclusion' => 'Alcanza los aprendizajes previstos para el periodo.'],
                ['level' => 'B', 'min' => 11, 'label' => 'En proceso', 'conclusion' => 'Está próximo a alcanzar los aprendizajes previstos y requiere acompañamiento.'],
                ['level' => 'C', 'min' => 0, 'label' => 'En inicio', 'conclusion' => 'Requiere mayor tiempo y acompañamiento para desarrollar los aprendizajes previstos.'],
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function down(): void
    {
        Schema::table('evaluations', fn (Blueprint $table) => $table->dropConstrainedForeignId('competency_id'));
        Schema::table('school_settings', fn (Blueprint $table) => $table->dropColumn([
            'achievement_rules',
            'director_name',
            'director_title',
            'tutor_signature_label',
            'report_card_footer',
        ]));
    }
};
