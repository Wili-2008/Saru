# API de SARU

Base: `http://saru.com/api/?route=RUTA`. GET consulta y POST modifica. Todas las operaciones POST requieren cookie de sesión y cabecera `X-CSRF-Token`, incluyendo instalación y login. Para obtener el token, consulta primero `sesion`; login y cambio de contraseña lo renuevan.

Formato JSON: `{ "estado": true, "message": "...", "data": {...} }`. Los PDF autorizados se entregan como `application/pdf`. No hay CORS porque el frontend y la API comparten origen.

## Sesiones y estudiantes

| Método | Ruta | Acceso y datos |
| --- | --- | --- |
| GET | `salud` | Público; verifica conexión y columnas de autenticación |
| GET | `sesion` | Público; usuario público o null y csrf |
| GET | `instalacion` | Público; estado de disponibilidad, sin clave |
| POST | `instalacion` | Solo durante instalación; token, nombres, apellidos, usuario, password |
| POST | `auth/login` | usuario, password |
| POST | `auth/logout` | Cookie actual y CSRF |
| POST | `auth/password` | Autenticado, incluso con cambio temporal; actual, nueva |
| GET | `cursos` | Administrador; cursos activos |
| GET | `resumen` | Administrador; estudiantes, materias, temas, cuestionarios |
| GET/POST | `estudiantes` | Administrador; consultar o crear con nombres, apellidos, usuario, curso_id, password |
| POST | `estudiantes/ID/datos` | Administrador; nombres, apellidos, usuario, curso_id |
| POST | `estudiantes/ID/estado` | Administrador; activo booleano |
| POST | `estudiantes/ID/password` | Administrador; password temporal |

## Administración de contenidos

| Método | Ruta | Datos POST |
| --- | --- | --- |
| GET/POST | `admin/materias` | curso_id, nombre, descripcion, activo |
| POST | `admin/materias/ID` | Mismos campos; edición o desactivación |
| GET/POST | `admin/temas` | materia_id, titulo, descripcion, orden, estado |
| POST | `admin/temas/ID` | Mismos campos; estado borrador/publicado |
| GET/POST | `admin/recursos` | tema_id, titulo, tipo, url, descripcion, activo |
| POST | `admin/recursos/ID` | Mismos campos; tipo video/libro/enlace |
| GET/POST | `admin/materiales` | Multipart: datos JSON y archivo PDF |
| POST | `admin/materiales/ID` | Multipart de edición; archivo opcional para reemplazar |
| GET/POST | `admin/cuestionarios` | tema_id, titulo, activo; creación inicialmente inactiva |
| POST | `admin/cuestionarios/ID` | titulo, activo; no cambia el tema asociado |
| GET/POST | `admin/cuestionarios/ID/preguntas` | enunciado, explicacion, activo, opciones |
| POST | `admin/preguntas/ID` | Mismos campos; edición o desactivación |
| GET | `admin/resultados` | Todos los intentos con estudiante, materia, tema, fecha y nota |

Todas estas rutas exigen rol administrador desde PHP. `opciones` es una lista de exactamente cuatro objetos `{texto, es_correcta}`; es_correcta es booleano y exactamente uno debe ser true. `activo` es booleano; identificadores y orden deben ser enteros positivos. Los nombres y textos se validan también en el servidor.

Multipart de material:

```text
datos: {"tema_id":1,"titulo":"Título real","descripcion":"Descripción","tipo":"guia","activo":true}
archivo: binario application/pdf
```

Tipos de material: resumen, guia, libro, otro. El servidor inspecciona el MIME real; no confía en el Content-Type enviado por el navegador. El nombre_guardado nunca se devuelve en las listas.

## Aprendizaje y evaluaciones

| Método | Ruta | Acceso y datos |
| --- | --- | --- |
| GET | `catalogo` | Estudiante; materias y temas visibles de su curso. Parámetros extra: q y materia_id |
| GET | `catalogo/temas/ID` | Tema visible con materiales, recursos y cuestionario activo. El administrador puede inspeccionar un borrador |
| GET | `materiales/ID/archivo` | Archivo autenticado. Estudiante: material activo y tema publicado de su curso. Administrador: previsualización |
| POST | `intentos` | Estudiante; cuestionario_id. Crea o retoma su intento en progreso |
| GET | `intentos/ID` | Propietario o administrador; copias inmutables y respuestas guardadas |
| POST | `intentos/ID/respuesta` | Estudiante propietario; pregunta_id y opcion_id de las copias |
| POST | `intentos/ID/finalizar` | Estudiante propietario; finalización idempotente con diez respuestas |
| GET | `historial` | Intentos de la cuenta autenticada; el administrador ve todos |

Las rutas de aprendizaje requieren haber cambiado la contraseña temporal. No se aceptan usuario_id, rol, nota, aciertos o claves correctas como autoridad del cliente. La identidad se obtiene de la sesión; los resultados se calculan con las opciones de las copias guardadas.

Antes de finalizar, `intentos/ID` omite explicacion y es_correcta. Después de finalizar las incorpora para la revisión. Las respuestas de un intento finalizado no se pueden actualizar.

## Estados HTTP

- 200: consulta u operación correcta.
- 201: registro/intentó creado o intento pendiente retomado por la ruta de inicio.
- 400: JSON o ruta mal formados.
- 401: sesión ausente, vencida, desactivada o revocada.
- 403: rol insuficiente, CSRF inválido o cambio temporal pendiente.
- 404: registro no encontrado o fuera de los permisos de la cuenta.
- 405: método no permitido.
- 409: duplicado, conflicto o intento ya finalizado al intentar responder.
- 413: cuerpo JSON demasiado grande.
- 415: contenido enviado en formato incorrecto.
- 422: validación, archivo inválido o evaluación incompleta.
- 429: demasiados intentos, con Retry-After.
- 503: configuración o servicio no disponible; detalle interno solo en el registro PHP.

La API tiene un límite de 64 KiB para JSON. Las cargas multipart se rigen por los límites de SARU y PHP. El cliente central usa rutas relativas al frontend, por lo que no contiene contraseñas, nombres físicos de archivos ni un dominio fijo.
