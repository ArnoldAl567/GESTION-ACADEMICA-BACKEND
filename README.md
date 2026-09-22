# EduGest Pro — Laravel y PostgreSQL

API de gestión académica para `GESTION_ACADEMICA_FRONTEND` (Angular). Laravel 13, PHP 8.3 o superior y PostgreSQL 17.

## Arranque local

La instalación de este equipo ya tiene `.env`, clave de aplicación, las bases `gestion_academica` y `gestion_academica_test`, migraciones y datos de demostración.

Terminal del backend:

```powershell
cd GESTION_ACADEMICA_BACKEND
php artisan serve --host=127.0.0.1 --port=8000
```

Otra terminal, para Angular:

```powershell
cd GESTION_ACADEMICA_FRONTEND
npm start
```

Abre http://localhost:4200. El proxy de Angular envía `/api/**` a Laravel. Si Angular estaba abierto antes del cambio, reinícialo para cargar `proxy.conf.json`.

## Acceso temporal sin login

En el entorno local está activo `AUTH_DEFAULT_ADMIN=true`: al abrir Angular, Laravel inicia automáticamente la sesión de `admin@colegio.edu.pe` y muestra el panel administrativo. La contraseña no se incluye en el frontend.

Este acceso debe desactivarse antes de publicar el sistema cambiando `AUTH_DEFAULT_ADMIN=false` y restaurando la pantalla de autenticación. Con la opción desactivada, `/api/v1/default-admin` responde como una ruta inexistente.

## Instalación en otro equipo

1. Instala PHP con las extensiones `pdo_pgsql`, `pgsql`, `mbstring`, `openssl` y `xml`, Composer y PostgreSQL.
2. Ejecuta `composer install`.
3. Crea las bases vacías `gestion_academica` y `gestion_academica_test` en PostgreSQL.
4. Copia `.env.example` a `.env` y configura `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`.
5. Ejecuta `php artisan key:generate` y `php artisan migrate --seed` para desarrollo.
6. En Angular ejecuta `npm install` y `npm start`.

No publiques `.env`. La contraseña de PostgreSQL solo está configurada allí. No se cambió la contraseña del servicio ni se modificaron bases de otros proyectos.

## Cuentas de demostración

| Perfil | Correo | Contraseña |
| --- | --- | --- |
| Administrador | admin@colegio.edu.pe | Admin2026! |
| Docente | docente@colegio.edu.pe | Docente2026! |
| Padre / Apoderado | padre@colegio.edu.pe | Padre2026! |

Selecciona el perfil correspondiente en el acceso. Las contraseñas se guardan con hash. El seeder solo permite entornos `local` y `testing`, y usa `firstOrCreate` para no sustituir registros existentes. No ejecutes el seeder de demostración en producción.

La base contiene 20 estudiantes ficticios, 10 docentes, 8 cursos, 18 aulas, matrículas, notas, asistencia y comunicados. Los indicadores reflejan esos registros reales; no reproducen los totales ficticios del diseño.

## Funcionalidades

- Autenticación por correo, contraseña y perfil; sesión persistente, cierre de sesión y cambio de contraseña.
- Estudiantes: registro, edición, búsqueda, paginación, estado, apoderado y vinculación con una cuenta de familia.
- Docentes: registro, edición, estado, especialidad y cuenta vinculada.
- Cursos: docente responsable, horas, área y aulas asignadas.
- Aulas: año, grado, sección y capacidad. Administración desde Configuración.
- Matrículas: estudiante, aula, año, fecha y estado; unicidad estudiante/año y control de vacantes.
- Asistencia diaria por aula con estados P/F/T/J. Guardado por lote en una transacción, sin duplicar registros al volver a guardar.
- Calificaciones por aula, curso y bimestre, escala 0–20; guardado por lote y consulta de promedios.
- Horarios: bloques semanales por curso y aula; evita superposiciones de aula o docente en el mismo año.
- Comunicados: redacción, borradores, publicación, destinatarios por perfil/aula, lectura y archivo individual.
- Dashboard: totales, distribución, asistencia, promedios, matrículas y alertas calculados desde PostgreSQL.
- Reportes de notas, asistencia, padrón, matrículas y carga docente; CSV compatible con Excel e impresión/guardar PDF desde el navegador.
- Configuración: institución, año, bimestre, usuarios y activación/desactivación de cuentas.

Los comunicados se entregan dentro del sistema; no envían correo externo. El PDF se obtiene mediante la opción de impresión del navegador. El archivo exportado para Excel es CSV, no XLSX.

## Permisos y consistencia

Los permisos se verifican en Laravel, además de ocultar acciones en Angular:
- Administradores: mantenimiento de registros y configuración.
- Docentes: estudiantes y matrículas de aulas asignadas; notas únicamente de sus cursos; asistencia de sus aulas y comunicados para sus familias.
- Apoderados: consulta únicamente de sus hijos, notas, asistencia, horarios y comunicados pertinentes. Sin escritura académica.
- Una cuenta desactivada pierde acceso incluso si ya tenía sesión iniciada.

Las claves foráneas impiden borrar registros con historial. El sistema devuelve conflicto 409 y permite cambiar su estado. Los errores de validación devuelven 422, acceso no autorizado 401/403 y registros inaccesibles 404.

## API

Prefijo: `/api/v1`. Las rutas usan el middleware web de Laravel: cookies de sesión y CSRF. No se guardan tokens de autenticación en el almacenamiento del navegador.

1. `GET /csrf` obtiene `csrf_token` y la cookie de sesión.
2. `POST /login` recibe `email`, `password`, `role` y `remember`, con cabecera `X-CSRF-TOKEN`.
3. Conserva las cookies y utiliza el nuevo `csrf_token` que devuelve el login para POST, PUT y DELETE.
4. `GET /me` consulta la sesión; `POST /logout` la invalida.

| Ruta | Operaciones |
| --- | --- |
| students, teachers, courses, classrooms, enrollments, schedules, communications | GET listado, POST crear, GET /{id}, PUT /{id}, DELETE /{id} |
| bootstrap | GET usuario, aulas, cursos, docentes, configuración y resumen |
| dashboard | GET con year y period |
| attendance | GET con classroom_id y date; PUT por lote |
| grades | GET con classroom_id y period; PUT por lote y course_id |
| communications/{id}/receipt | PUT lectura y archived opcional |
| reports | GET con type, year y period |
| reports/export | GET mismos filtros, descarga CSV |
| settings | PUT configuración |
| users | GET listado, POST crear, PUT /{id} activar/desactivar |
| password | PUT current_password, password y password_confirmation |

Listados paginados: `page`, `per_page` (1–100), `q` para entidades con nombre, y `status` para estudiantes, docentes y matrículas. Matrículas y aulas aceptan `year`; matrículas y horarios aceptan `classroom_id`.

Tipos de reporte: `academic`, `attendance`, `students`, `enrollment`, `teachers`.

Asistencia:
```json
{
  "classroom_id": 13,
  "date": "2026-09-10",
  "records": [{"enrollment_id": 1, "status": "P"}]
}
```

Notas:
```json
{
  "classroom_id": 13,
  "course_id": 1,
  "period": 3,
  "records": [{"enrollment_id": 1, "score": 18.5}]
}
```

Los identificadores de los ejemplos deben sustituirse por los devueltos por la API. Para listar rutas exactas: `php artisan route:list --path=api`.

## Base de datos

Las migraciones son la fuente de instalación y actualización. `database/academic-schema.sql` es una copia de referencia de la estructura PostgreSQL, sin datos ni contraseñas. No debe importarse encima de una base ya migrada.

Tablas académicas: `students`, `teachers`, `classrooms`, `courses`, `classroom_course`, `enrollments`, `attendances`, `grades`, `schedules`, `communications`, `communication_user` y `school_settings`, además de usuarios y tablas de Laravel.

## Pruebas

```powershell
php vendor/bin/phpunit tests/Feature/AcademicApiTest.php
```

Las pruebas usan PostgreSQL y solo pueden migrar `gestion_academica_test`. Nunca apuntan a `gestion_academica`. Cubren autenticación, permisos por perfil, CRUD, referencias, vacantes, notas, asistencia, horarios, comunicados, reportes y desactivación de usuarios.

## Compilación Angular y ejecución SSR

```powershell
npm run build
npm run serve:ssr:GESTION_ACADEMICA_FRONTEND
```

La versión SSR se abre en http://localhost:4000 y también reenvía `/api` a Laravel. Puede configurarse `BACKEND_URL` en el proceso Node (por defecto http://127.0.0.1:8000).

La lista `security.allowedHosts` de `angular.json` permite los nombres locales. Antes de publicar SSR, añade el dominio real de la institución y vuelve a compilar; no uses un comodín global.

Para publicar, configura una base dedicada, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS y cookies seguras; ejecuta solo migraciones y crea usuarios reales. El servidor integrado de PHP y las cuentas de demostración son para desarrollo.
