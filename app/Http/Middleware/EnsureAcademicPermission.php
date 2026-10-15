<?php

namespace App\Http\Middleware;

use App\Services\AcademicPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademicPermission
{
    public function __construct(private AcademicPermissions $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $parts = $request->segments();
        $resource = $parts[2] ?? '';
        $subresource = $parts[3] ?? '';

        if (in_array($resource, ['me', 'logout', 'password'], true)) {
            return $next($request);
        }

        if ($resource === 'guardian-profile') {
            $this->permissions->authorize($request->user(), 'guardians', $request->isMethod('get') ? 'viewOwn' : 'updateOwn');

            return $next($request);
        }

        [$module, $action] = match ($resource) {
            'bootstrap', 'dashboard' => ['dashboard', 'view'],
            'settings' => ['roles', 'update'],
            'users', 'roles' => ['roles', $this->restAction($request)],
            'reports' => ['reports', $subresource === 'export' ? 'export' : 'view'],
            'attendance' => ['attendance', $request->isMethod('put') ? 'update' : 'view'],
            'grades' => ['grades', $request->isMethod('put') ? 'update' : 'view'],
            'report-cards' => ['tracking', ($parts[4] ?? '') === 'pdf' ? 'export' : 'view'],
            'enrollment-candidates' => ['enrollment', 'create'],
            'management' => [$this->managementModule($subresource), $this->restAction($request)],
            'students' => ['students', $this->restAction($request)],
            'guardians' => ['guardians', $this->restAction($request)],
            'teachers' => ['teachers', $this->restAction($request)],
            'courses' => ['courses', $this->restAction($request)],
            'classrooms' => ['classrooms', $this->restAction($request)],
            'enrollments' => ['enrollment', ($parts[4] ?? '') === 'activation' ? 'activate' : $this->restAction($request)],
            'schedules' => ['schedule', $this->restAction($request)],
            'communications' => ['communications', ($parts[4] ?? '') === 'receipt' ? 'receipt' : $this->restAction($request)],
            'academic-years', 'academic-periods' => ['academicCalendar', ($parts[4] ?? '') === 'activation' ? 'activate' : $this->restAction($request)],
            'evaluations' => ['evaluations', ($parts[4] ?? '') === 'scores' ? 'update' : $this->restAction($request)],
            default => ['', ''],
        };

        // Las observaciones sin publicar son internas; la familia ve la libreta cerrada.
        if ($resource === 'management' && $subresource === 'student-observations' && $request->user()->accessScope() === 'padre') {
            abort(403, 'Las observaciones internas no están disponibles para las familias.');
        }

        $this->permissions->authorize($request->user(), $module, $action);

        return $next($request);
    }

    private function restAction(Request $request): string
    {
        return match ($request->method()) {
            'GET', 'HEAD' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => '',
        };
    }

    private function managementModule(string $resource): string
    {
        return match ($resource) {
            'administrative-staff' => 'personnel',
            'competencies' => 'courses',
            'teacher-assignments' => 'teacherAssignments',
            'academic-events' => 'events',
            'evaluations' => 'evaluations',
            'student-documents' => 'students',
            'student-observations' => 'tracking',
            default => '',
        };
    }
}
