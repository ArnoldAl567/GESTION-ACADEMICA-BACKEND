# Estructura del backend

El backend usa Laravel y separa cada módulo por responsabilidad. Las carpetas propias añadidas al proyecto se nombran en español; se mantienen los nombres obligatorios de Laravel como `Controllers`, `Models` y `Requests` para no romper sus convenciones.

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── Academico/
│   │       │   ├── Compartido/ControladorCrudInstitucional.php
│   │       │   ├── PanelController.php
│   │       │   ├── AsistenciaController.php
│   │       │   ├── CalificacionesController.php
│   │       │   ├── ComunicacionesController.php
│   │       │   ├── ConfiguracionController.php
│   │       │   ├── EvaluacionesController.php
│   │       │   ├── SeguimientoAcademicoController.php
│   │       │   └── controladores de cada mantenedor institucional
│   │       ├── AcademicController.php      # CRUD común de registros
│   │       └── AuthController.php
│   ├── Requests/                            # Solicitudes históricas de Laravel
│   └── Solicitudes/Academico/               # Validaciones por caso de uso
├── Models/                                 # Modelos Eloquent
├── Services/                               # Acceso por perfil y resúmenes existentes
└── Servicios/
    └── Academico/
        ├── ReglasAcademicasServicio.php
        ├── EvaluacionesServicio.php
        └── SeguimientoAcademicoServicio.php
```

## Responsabilidad de cada controlador

- `PanelController`: datos iniciales y resumen del dashboard.
- `AsistenciaController`: consulta y guardado masivo de asistencia.
- `CalificacionesController`: consulta y guardado masivo de notas.
- `ComunicacionesController`: lectura y archivado por usuario.
- `ReportesController`: vista JSON y exportación CSV.
- `ConfiguracionController`: institución, periodo activo y usuarios.
- `PersonalAdministrativoController`, `CompetenciasController`, `AsignacionesDocentesController`, `EventosAcademicosController` y `DocumentosEstudiantesController`: mantenedores específicos.
- `EvaluacionesController`: instrumentos de evaluación y registro de calificaciones.
- `SeguimientoAcademicoController`: observaciones y libretas académicas.
- `ControladorCrudInstitucional`: solo concentra paginación y persistencia repetitiva; no contiene reglas de negocio.
- `AcademicController`: listado, detalle, creación, edición y eliminación de recursos que comparten el mismo contrato CRUD.

## Flujo de una solicitud

```text
routes/api.php
  → middleware de sesión y usuario activo
  → controlador del módulo
  → servicio de acceso/consulta/reglas
  → modelo Eloquent
  → PostgreSQL
```

## Convenciones

- Los controladores coordinan la petición; las reglas reutilizables pertenecen a servicios.
- Cada clase de `Http/Solicitudes/Academico` valida un caso de uso antes de ejecutar el controlador.
- `AcademicAccess` limita los datos según administrador, docente o apoderado.
- Las transacciones protegen operaciones que actualizan varios registros.
- Los nombres de tablas y atributos permanecen en inglés para conservar compatibilidad con la base existente y la API.

Antes de entregar un cambio, ejecuta `vendor/bin/pint --format agent` y `php artisan test --compact`.
