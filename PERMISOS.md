# Permisos del sistema

Los permisos se definen por módulo y acción en `app/Services/AcademicPermissions.php`.
El middleware `EnsureAcademicPermission` verifica cada petición antes de ejecutar el controlador. Angular recibe la matriz efectiva en `GET /api/v1/bootstrap` para mostrar únicamente las opciones permitidas. Ocultar un botón no sustituye la verificación del servidor.

| Área | Administrador | Docente titular | Apoderado |
| --- | --- | --- | --- |
| Usuarios, roles, personal y configuración | Gestión completa | Sin acceso | Sin acceso |
| Años, periodos, aulas y cursos | Gestión completa | Consulta | Consulta de la información vinculada a sus hijas |
| Matrículas | Gestión completa | Consulta de su aula | Consulta de sus hijas |
| Asistencia | Consulta y registro | Consulta y registro de su aula | Consulta de sus hijas |
| Horarios y asignación docente | Gestión completa | Consulta de su aula | Consulta del horario de sus hijas |
| Evaluaciones y notas | Gestión completa mientras el periodo esté abierto | Crea evaluaciones y registra notas de su aula y periodo asignado | Consulta resultados calificados tras el cierre del periodo |
| Libretas y seguimiento | Gestión completa | Observaciones y libretas de su aula | Libretas publicadas de sus hijas |
| Estudiantes y apoderados | Gestión completa | Consulta de alumnas y contactos de su aula | Consulta de sus hijas y edición de sus propios datos de contacto |
| Calendario y eventos | Gestión completa | Consulta | Consulta de eventos publicados de su aula o institucionales |
| Comunicados | Gestión completa | Publica para su aula y consulta los visibles | Consulta y confirma recepción |
| Reportes | Consulta y exportación | Consulta y exportación de datos de su aula | Sin acceso |

Una docente obtiene acceso a través de la asignación activa al aula; para registrar notas se exige además la asignación al curso y periodo concreto. Una persona apoderada solo accede a alumnas vinculadas con su cuenta. Los resultados y libretas no se muestran a las familias mientras el periodo siga abierto. El cierre del periodo impide modificar evaluaciones y calificaciones.

Los roles adicionales creados en el mantenedor heredan uno de los tres niveles (`admin`, `docente` o `padre`); por ahora no tienen excepciones individuales por acción. Toda nueva ruta académica debe incorporarse al middleware y a esta matriz antes de exponerse.
