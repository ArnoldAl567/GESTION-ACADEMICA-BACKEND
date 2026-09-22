<?php

use App\Http\Controllers\Api\AcademicController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->name('api.')->group(function () {
    Route::get('csrf', [AuthController::class, 'csrf'])->name('csrf');
    Route::post('default-admin', [AuthController::class, 'defaultAdmin'])->middleware('throttle:20,1')->name('default-admin');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
    Route::middleware(['auth', EnsureActiveUser::class, 'throttle:120,1'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::put('password', [AuthController::class, 'password'])->name('password');
        Route::get('bootstrap', [AcademicController::class, 'bootstrap'])->name('bootstrap');
        Route::get('dashboard', [AcademicController::class, 'dashboard'])->name('dashboard');
        Route::get('reports', [AcademicController::class, 'report'])->name('reports');
        Route::get('reports/export', [AcademicController::class, 'export'])->name('reports.export');
        Route::get('attendance', [AcademicController::class, 'attendance'])->name('attendance');
        Route::put('attendance', [AcademicController::class, 'saveAttendance'])->name('attendance.save');
        Route::get('grades', [AcademicController::class, 'grades'])->name('grades');
        Route::put('grades', [AcademicController::class, 'saveGrades'])->name('grades.save');
        Route::put('communications/{id}/receipt', [AcademicController::class, 'receipt'])->whereNumber('id')->name('communications.receipt');
        Route::put('settings', [AcademicController::class, 'settings'])->name('settings');
        Route::get('users', [AcademicController::class, 'users'])->name('users');
        Route::post('users', [AcademicController::class, 'createUser'])->name('users.store');
        Route::put('users/{id}', [AcademicController::class, 'updateUser'])->whereNumber('id')->name('users.update');
        Route::where(['resource' => 'students|teachers|courses|classrooms|enrollments|schedules|communications|academic-years|academic-periods'])->group(function () {
            Route::get('{resource}', [AcademicController::class, 'index'])->name('index');
            Route::post('{resource}', [AcademicController::class, 'store'])->name('store');
            Route::get('{resource}/{id}', [AcademicController::class, 'show'])->whereNumber('id')->name('show');
            Route::put('{resource}/{id}', [AcademicController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{resource}/{id}', [AcademicController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
    });
});
