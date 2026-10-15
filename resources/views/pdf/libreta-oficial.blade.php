<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libreta oficial - {{ $libreta['student']->name }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { border-bottom: 3px solid #2563eb; margin-bottom: 16px; padding-bottom: 12px; text-align: center; }
        .logo { float: left; height: 72px; width: 58px; object-fit: contain; }
        h1 { font-size: 17px; margin: 4px 0; }
        h2 { color: #2563eb; font-size: 13px; margin: 16px 0 7px; }
        .subtitle { color: #64748b; }
        .clear { clear: both; }
        .student { background: #eff6ff; border-radius: 6px; padding: 10px; }
        .student table, .summary { width: 100%; }
        table { border-collapse: collapse; }
        .results { width: 100%; }
        .results th { background: #1e3a8a; color: white; padding: 7px; text-align: left; }
        .results td { border: 1px solid #dbe3ef; padding: 7px; vertical-align: top; }
        .level { color: #1d4ed8; font-weight: bold; text-align: center; }
        .box { border: 1px solid #dbe3ef; border-radius: 5px; margin-top: 8px; padding: 9px; }
        .attendance td { border: 1px solid #dbe3ef; padding: 7px; text-align: center; }
        .signatures { margin-top: 42px; width: 100%; }
        .signature { display: inline-block; text-align: center; width: 46%; }
        .line { border-top: 1px solid #334155; margin: 0 auto 5px; width: 75%; }
        .footer { bottom: 8px; color: #64748b; font-size: 8px; position: fixed; text-align: center; width: 100%; }
    </style>
</head>
<body>
<header class="header">
    @if(is_file($logo))<img class="logo" src="{{ $logo }}" alt="Escudo">@endif
    <h1>{{ $institucion->name ?? 'Institución Educativa N° 80404' }}</h1>
    <strong>LIBRETA OFICIAL DE INFORMACIÓN ACADÉMICA</strong><br>
    <span class="subtitle">{{ $libreta['academic_period']->name }} · Año académico {{ $libreta['classroom']->year }}</span>
    <div class="clear"></div>
</header>

<section class="student">
    <table>
        <tr><td><strong>Estudiante:</strong> {{ $libreta['student']->name }}</td><td><strong>DNI:</strong> {{ $libreta['student']->dni }}</td></tr>
        <tr><td><strong>Grado:</strong> {{ $libreta['classroom']->grade }}° de primaria</td><td><strong>Sección:</strong> {{ $libreta['classroom']->section }}</td></tr>
    </table>
</section>

<h2>Resultados por curso</h2>
<table class="results">
    <thead><tr><th>Área / curso</th><th>Promedio</th><th>Nivel</th><th>Avance de evaluación</th></tr></thead>
    <tbody>
    @forelse($libreta['course_averages'] as $course)
        <tr>
            <td>{{ $course['course'] }}</td>
            <td>{{ $course['average'] === null ? '—' : number_format($course['average'], 2) }}</td>
            <td class="level">{{ $course['achievement_level'] ?? '—' }}<br><small>{{ $course['achievement_label'] ?? '' }}</small></td>
            <td>{{ number_format($course['weight_evaluated'], 0) }}% evaluado / {{ number_format($course['weight_total'], 0) }}% programado</td>
        </tr>
    @empty
        <tr><td colspan="4">Sin calificaciones registradas en el periodo.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Resultados por competencia</h2>
<table class="results">
    <thead><tr><th>Curso</th><th>Competencia</th><th>Promedio</th><th>Nivel</th></tr></thead>
    <tbody>
    @forelse($libreta['competency_averages'] as $competency)
        <tr><td>{{ $competency['course'] }}</td><td>{{ $competency['competency'] }}</td><td>{{ number_format($competency['average'], 2) }}</td><td class="level">{{ $competency['achievement_level'] }}</td></tr>
    @empty
        <tr><td colspan="4">Sin resultados vinculados a competencias.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Resumen del periodo</h2>
<div class="box"><strong>Promedio general:</strong> {{ $libreta['average'] === null ? '—' : number_format($libreta['average'], 2) }} &nbsp; <strong>Nivel:</strong> {{ $libreta['achievement_level'] ?? '—' }} — {{ $libreta['achievement_label'] ?? '' }}</div>
<div class="box"><strong>Conclusión descriptiva:</strong><br>{{ $libreta['descriptive_conclusion'] }}</div>
<div class="box"><strong>Conducta:</strong><br>{{ $libreta['conduct'] }}</div>

<h2>Asistencia</h2>
<table class="summary attendance"><tr><td>Presentes<br><strong>{{ $libreta['attendance_detail']['present'] }}</strong></td><td>Tardanzas<br><strong>{{ $libreta['attendance_detail']['late'] }}</strong></td><td>Faltas<br><strong>{{ $libreta['attendance_detail']['absent'] }}</strong></td><td>Justificadas<br><strong>{{ $libreta['attendance_detail']['justified'] }}</strong></td><td>Asistencia<br><strong>{{ $libreta['attendance'] === null ? '—' : $libreta['attendance'].'%' }}</strong></td></tr></table>

<section class="signatures">
    <div class="signature"><div class="line"></div>{{ $institucion->tutor_signature_label ?? 'Docente tutor(a)' }}</div>
    <div class="signature"><div class="line"></div>{{ $institucion->director_name ?? '' }}<br>{{ $institucion->director_title ?? 'Director(a)' }}</div>
</section>

<footer class="footer">{{ $institucion->report_card_footer ?? 'Documento generado por el Sistema de Gestión Académica.' }}</footer>
</body>
</html>
