# Acceso y uso de SARU

## Entrar al sistema

1. Inicia WampServer y confirma que Apache y MySQL estén activos.
2. Abre **http://saru.com/** en el navegador.
3. Usa los datos de acceso entregados al finalizar el proyecto. Existe una copia privada en `C:\Saru\storage\accesos-iniciales.json`; ese archivo no se sirve por web.
4. El mismo formulario distingue el rol y dirige al panel correspondiente.

Se crearon las cuentas `admin.saru` (Administrador SARU) y `estudiante.saru` (Estudiante Inicial), con contraseñas aleatorias. No hay registro público. El estudiante está asignado a Sexto de secundaria y tiene habilitado el cambio obligatorio de su contraseña temporal.

Para probar ambos roles a la vez, usa una ventana normal para el administrador y una ventana privada para el estudiante. Las pestañas normales del mismo navegador comparten sesión.

## Administrador

### Estudiantes

1. Entra en **Estudiantes → Nuevo estudiante**.
2. Completa nombres, apellidos, usuario único, curso y contraseña temporal; confírmala.
3. Entrega las credenciales de forma privada al estudiante.
4. Usa el buscador y el filtro para consultar cuentas.
5. **Editar** cambia nombres, apellidos, usuario o curso.
6. **Desactivar** corta el acceso sin borrar los datos. **Activar** lo habilita nuevamente.
7. **Contraseña** establece una nueva temporal e invalida las sesiones anteriores. El estudiante deberá cambiarla al ingresar.

Las contraseñas deben tener al menos 12 caracteres y como máximo 72 bytes. Una frase larga es adecuada; los caracteres acentuados pueden ocupar varios bytes.

### Materias y temas

1. Abre **Contenidos → Materias** para agregar, editar o desactivar materias.
2. En **Temas**, agrega un título, selecciona la materia, completa la descripción, el orden y el estado.
3. Conserva el tema en **Borrador** mientras preparas sus materiales y cuestionario.
4. Cuando esté listo, pulsa **Editar** y cambia su estado a **Publicado**.
5. Si necesitas retirarlo del catálogo, vuelve a Borrador. El historial ya generado se conserva.

Los tres temas originales ya existen, todavía en borrador:

- Historia / Ciencias Sociales: Independencia de Bolivia.
- Matemáticas: Ecuaciones de segundo grado.
- Física: Leyes de Newton.

El estudiante ve únicamente las materias activas de su curso y los temas publicados. No se inventaron textos, PDF o preguntas para estos temas; carga el contenido educativo real de tu institución.

### PDF y recursos

1. En **Contenidos → Materiales PDF → Agregar PDF**, selecciona el tema.
2. Completa título, tipo y descripción y selecciona el documento.
3. El archivo debe ser un PDF real y respetar el tamaño permitido por SARU y PHP.
4. **Ver PDF** permite previsualizarlo como administrador.
5. **Editar** permite modificar la ficha, desactivarla o reemplazar el archivo.
6. En **Enlaces y recursos**, registra video, libro o enlace con dirección HTTP o HTTPS.

Un tema admite varios materiales y enlaces. No se muestra un material desactivado al estudiante, y tampoco puede obtenerlo usando una URL anterior. Los nombres físicos y la carpeta de almacenamiento permanecen privados.

### Cuestionarios y banco de preguntas

1. Abre **Cuestionarios → Nuevo cuestionario**.
2. Selecciona el tema y escribe el título. Déjalo inactivo mientras lo preparas.
3. Entra en **Banco de preguntas → Nueva pregunta**.
4. Escribe el enunciado y exactamente cuatro alternativas; marca una sola como correcta.
5. Añade una explicación que ayude a aprender de la respuesta. Se mostrará después de finalizar.
6. Mantén la pregunta activa para que pueda seleccionarse.
7. Repite hasta tener como mínimo diez preguntas válidas. Se recomiendan entre 15 y 20.
8. Vuelve a **Todos los cuestionarios → Editar**, marca activo y guarda.

Cada tema admite un único cuestionario. Si una edición deja menos de diez preguntas activas válidas, se desactiva automáticamente. Después de completar nuevamente el banco, actívalo manualmente. Las evaluaciones anteriores conservan sus propias copias de las preguntas y respuestas.

### Resultados

En **Resultados** puedes buscar por estudiante, usuario, tema o materia y filtrar por estado. Cada fila muestra fecha, aciertos y nota. **Ver revisión** permite consultar las diez preguntas y respuestas de una evaluación finalizada. Las evaluaciones en progreso todavía no tienen nota.

### Tu contraseña y salida

En **Mi contraseña**, introduce la actual, la nueva y su confirmación. Usa **Cerrar sesión** para terminar el acceso. El instalador inicial ya está bloqueado y no sirve para recuperar una contraseña existente.

## Estudiante

1. En el primer acceso, cambia obligatoriamente la contraseña temporal recibida.
2. En **Mis materias**, pulsa una materia o busca un tema por título o descripción.
3. Abre el tema y consulta sus materiales y recursos.
4. **Leer PDF** abre el visor. Si tu navegador no lo muestra allí, usa **Abrir PDF en otra pestaña**.
5. Cuando el cuestionario esté activo, pulsa **Comenzar o retomar**.
6. Responde una pregunta a la vez. La elección se guarda automáticamente; espera el mensaje de guardado antes de cerrar la página.
7. Usa **Anterior**, **Siguiente** o los números para navegar y cambiar respuestas.
8. Al llegar al final, revisa el resumen. Puedes volver a cualquier pregunta.
9. Con las diez respuestas completas, pulsa **Finalizar evaluación**.
10. Verás aciertos, nota sobre 100, respuestas correctas y explicaciones. Después de finalizar ya no puedes cambiar ese intento.

No hay cronómetro, límite de intentos ni una nota mínima de aprobación configurada. Cada respuesta correcta vale diez puntos.

En **Mi historial** puedes retomar intentos pendientes o consultar los resultados anteriores. Después de finalizar, puedes repetir desde el tema y se generará un intento nuevo.

Si la sesión vence por inactividad, vuelve a iniciar sesión y retoma el intento desde el historial. Las respuestas que llegaron a mostrar el mensaje de guardado permanecen en MySQL.

## Estado inicial de la entrega

Hay una cuenta administrativa y una estudiantil activas. Los tres temas suministrados permanecen en borrador, sin PDF ni preguntas precargadas. Para que aparezcan en el panel estudiantil, carga los contenidos reales y publica los temas siguiendo los pasos de esta guía.

## Copias de respaldo

Respalda `dbsaru` desde Navicat y la carpeta `storage/materiales/` en conjunto. Conserva la configuración local de forma privada. Las credenciales iniciales son solo para el acceso inicial: si cambias una contraseña, el archivo de entrega no se actualiza automáticamente.
