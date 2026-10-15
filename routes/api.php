<?php

use App\Http\Controllers\Api\AcademicController;
use App\Http\Controllers\Api\Academico\AsignacionesDocentesController;
use App\Http\Controllers\Api\Academico\AsistenciaController;
use App\Http\Controllers\Api\Academico\CalendarioAcademicoController;
use App\Http\Controllers\Api\Academico\CalificacionesController;
use App\Http\Controllers\Api\Academico\CompetenciasController;
use App\Http\Controllers\Api\Academico\ComunicacionesController;
use App\Http\Controllers\Api\Academico\ConfiguracionController;
use App\Http\Controllers\Api\Academico\DocumentosEstudiantesController;
use App\Http\Controllers\Api\Academico\EvaluacionesController;
use App\Http\Controllers\Api\Academico\EventosAcademicosController;
use App\Http\Controllers\Api\Academico\MatriculasController;
use App\Http\Controllers\Api\Academico\PanelController;
use App\Http\Controllers\Api\Academico\PerfilApoderadoController;
use App\Http\Controllers\Api\Academico\PersonalAdministrativoController;
use App\Http\Controllers\Api\Academico\ReportesController;
use App\Http\Controllers\Api\Academico\RolesController;
use App\Http\Controllers\Api\Academico\SeguimientoAcademicoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAcademicPermission;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->name('api.')->group(function () {
    Route::get('csrf', [AuthController::class, 'csrf'])->name('csrf');
    Route::post('default-admin', [AuthController::class, 'defaultAdmin'])->middleware('throttle:20,1')->name('default-admin');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
    Route::middleware(['auth', EnsureActiveUser::class, EnsureAcademicPermission::class, 'throttle:120,1'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::put('password', [AuthController::class, 'password'])->name('password');
        Route::get('guardian-profile', [PerfilApoderadoController::class, 'mostrar'])->name('guardian-profile.show');
        Route::put('guardian-profile', [PerfilApoderadoController::class, 'actualizar'])->name('guardian-profile.update');
        Route::get('bootstrap', [PanelController::class, 'datosIniciales'])->name('bootstrap');
        Route::get('dashboard', [PanelController::class, 'resumen'])->name('dashboard');
        Route::get('reports', [ReportesController::class, 'mostrar'])->name('reports');
        Route::get('reports/export', [ReportesController::class, 'exportar'])->name('reports.export');
        Route::get('attendance', [AsistenciaController::class, 'listar'])->name('attendance');
        Route::put('attendance', [AsistenciaController::class, 'guardar'])->name('attendance.save');
        Route::get('grades', [CalificacionesController::class, 'listar'])->name('grades');
        Route::put('grades', [CalificacionesController::class, 'guardar'])->name('grades.save');
        Route::put('communications/{id}/receipt', [ComunicacionesController::class, 'actualizarRecepcion'])->whereNumber('id')->name('communications.receipt');
        Route::put('settings', [ConfiguracionController::class, 'guardar'])->name('settings');
        Route::get('users', [ConfiguracionController::class, 'listarUsuarios'])->name('users');
        Route::post('users', [ConfiguracionController::class, 'crearUsuario'])->name('users.store');
        Route::put('users/{id}', [ConfiguracionController::class, 'actualizarUsuario'])->whereNumber('id')->name('users.update');
        Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
        Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
        Route::put('roles/{id}', [RolesController::class, 'update'])->whereNumber('id')->name('roles.update');
        Route::put('roles/{id}/activation', [RolesController::class, 'activation'])->whereNumber('id')->name('roles.activation');
        Route::delete('roles/{id}', [RolesController::class, 'destroy'])->whereNumber('id')->name('roles.destroy');
        Route::get('enrollment-candidates', [MatriculasController::class, 'candidatos'])->name('enrollments.candidates');
        Route::put('enrollments/{id}/activation', [MatriculasController::class, 'actualizarEstado'])->whereNumber('id')->name('enrollments.activation');
        Route::put('academic-years/{id}/activation', [CalendarioAcademicoController::class, 'actualizarEstadoAnio'])->whereNumber('id')->name('academic-years.activation');
        Route::put('academic-periods/{id}/activation', [CalendarioAcademicoController::class, 'actualizarEstadoPeriodo'])->whereNumber('id')->name('academic-periods.activation');
        Route::put('evaluations/{evaluation}/scores', [EvaluacionesController::class, 'scores'])->name('evaluations.scores');
        Route::get('report-cards', [SeguimientoAcademicoController::class, 'reportCards'])->name('report-cards');
        Route::get('report-cards/{enrollment}/pdf', [SeguimientoAcademicoController::class, 'pdf'])->whereNumber('enrollment')->name('report-cards.pdf');

        Route::prefix('management')->group(function () {
            Route::apiResource('administrative-staff', PersonalAdministrativoController::class)->except('show')->parameters(['administrative-staff' => 'id']);
            Route::apiResource('competencies', CompetenciasController::class)->except('show')->parameters(['competencies' => 'id']);
            Route::apiResource('teacher-assignments', AsignacionesDocentesController::class)->except('show')->parameters(['teacher-assignments' => 'id']);
            Route::apiResource('academic-events', EventosAcademicosController::class)->except('show')->parameters(['academic-events' => 'id']);
            Route::apiResource('evaluations', EvaluacionesController::class)->except('show')->parameters(['evaluations' => 'id']);
            Route::apiResource('student-documents', DocumentosEstudiantesController::class)->except('show')->parameters(['student-documents' => 'id']);
            Route::apiResource('student-observations', SeguimientoAcademicoController::class)->except('show')->parameters(['student-observations' => 'id']);
        });
        Route::where(['resource' => 'students|guardians|teachers|courses|classrooms|enrollments|schedules|communications|academic-years|academic-periods'])->group(function () {
            Route::get('{resource}', [AcademicController::class, 'index'])->name('index');
            Route::post('{resource}', [AcademicController::class, 'store'])->name('store');
            Route::get('{resource}/{id}', [AcademicController::class, 'show'])->whereNumber('id')->name('show');
            Route::put('{resource}/{id}', [AcademicController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{resource}/{id}', [AcademicController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
    });
});
