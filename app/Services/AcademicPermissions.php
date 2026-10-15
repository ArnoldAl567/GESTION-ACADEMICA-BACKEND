<?php

namespace App\Services;

use App\Models\User;

class AcademicPermissions
{
    public const MODULES = [
        'dashboard', 'enrollment', 'attendance', 'schedule', 'teacherAssignments',
        'events', 'evaluations', 'grades', 'tracking', 'students', 'guardians',
        'teachers', 'personnel', 'academicCalendar', 'classrooms', 'courses',
        'communications', 'reports', 'roles',
    ];

    private const TEACHER = [
        'dashboard' => ['view'],
        'enrollment' => ['view'],
        'attendance' => ['view', 'update'],
        'schedule' => ['view', 'create', 'update', 'delete'],
        'teacherAssignments' => ['view'],
        'events' => ['view'],
        'evaluations' => ['view', 'create', 'update'],
        'grades' => ['view', 'update'],
        'tracking' => ['view', 'create', 'update', 'export'],
        'students' => ['view'],
        'guardians' => ['view'],
        'teachers' => ['view'],
        'academicCalendar' => ['view'],
        'classrooms' => ['view'],
        'courses' => ['view'],
        'communications' => ['view', 'create', 'update', 'receipt'],
        'reports' => ['view', 'export'],
    ];

    private const GUARDIAN = [
        'dashboard' => ['view'],
        'enrollment' => ['view'],
        'attendance' => ['view'],
        'schedule' => ['view'],
        'events' => ['view'],
        'grades' => ['view'],
        'tracking' => ['view', 'export'],
        'students' => ['view'],
        'guardians' => ['viewOwn', 'updateOwn'],
        'communications' => ['view', 'receipt'],
    ];

    private const ADMIN_ACTIONS = ['view', 'create', 'update', 'delete', 'export', 'activate', 'receipt'];

    public function allows(User $user, string $module, string $action = 'view'): bool
    {
        if (! $user->active || ! $user->hasActiveRole() || ! in_array($module, self::MODULES, true)) {
            return false;
        }

        return in_array($action, $this->forModule($user, $module), true);
    }

    public function authorize(User $user, string $module, string $action = 'view'): void
    {
        abort_unless($this->allows($user, $module, $action), 403, 'No tienes permiso para esta acción.');
    }

    /** @return array<string, array<int, string>> */
    public function forUser(User $user): array
    {
        return collect(self::MODULES)
            ->mapWithKeys(fn (string $module) => [$module => $this->forModule($user, $module)])
            ->all();
    }

    /** @return array<int, string> */
    private function forModule(User $user, string $module): array
    {
        if (! $user->active || ! $user->hasActiveRole()) {
            return [];
        }

        return match ($user->accessScope()) {
            'admin' => self::ADMIN_ACTIONS,
            'docente' => self::TEACHER[$module] ?? [],
            'padre' => self::GUARDIAN[$module] ?? [],
            default => [],
        };
    }
}
