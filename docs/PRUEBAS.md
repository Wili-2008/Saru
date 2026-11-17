# Verificación de SARU · 22 de octubre de 2026

## Entorno comprobado

- Windows y WampServer del usuario.
- Apache 2.4.65; PHP web 8.3.28.
- Herramientas de consola con PHP 8.4.15.
- MySQL real `dbsaru`, con las 13 tablas originales.
- Dominio local `http://saru.com/`, VirtualHost apuntando al frontend y Alias `/api/`.
- Chrome automatizado con Playwright, escritorio 1440 × 1000 y móvil 390 × 844.

## Resultados

| Conjunto | Comprobaciones | Alcance |
| --- | ---: | --- |
| `tests/backend.php` | 39 | SQLite aislado; instalación, contraseñas, sesiones, permisos y estudiantes |
| `tests/api.php` | 5 | Entrada PHP real en CLI; JSON, CSRF, sesión, rutas y métodos |
| `tests/mysql.php` | 42 | MySQL real con transacción exterior revertida y savepoints para las transacciones de controladores |
| `tests/frontend.cjs` | 16 | Interfaz con API simulada; flujos de administrador y estudiante |
| `tests/wamp.cjs` | 32 | Navegador → Apache → PHP → MySQL, con archivos y datos temporales |

También se revisó la sintaxis de PHP y JavaScript y la configuración Apache con `httpd.exe -t` (`Syntax OK`).

### Evaluaciones verificadas en MySQL

- Impide activar un banco sin diez preguntas válidas.
- Requiere cuatro alternativas y una correcta.
- Selecciona diez preguntas distintas de un banco mayor y mezcla opciones.
- No devuelve claves ni explicaciones antes de finalizar.
- Verifica propiedad de intento, pregunta y opción.
- Guarda y cambia respuestas; conserva el estado al retomar.
- Requiere diez respuestas para finalizar.
- Calcula 90/100 al acertar nueve en la prueba de controladores.
- Finalización repetida mantiene el mismo resultado.
- Impide cambiar respuestas después de finalizar.
- Editar preguntas de origen no modifica las copias históricas.
- Permite repetir y conserva los dos intentos.
- Desactiva el cuestionario al bajar de diez preguntas válidas.
- Mantiene los datos de prueba en una transacción que finalmente se revierte.

### Flujos comprobados en WampServer

- Login real de administrador y estudiante.
- Cambio obligatorio de contraseña temporal y cierre de sesión real.
- Materias, temas en borrador y publicación.
- Carga y lectura de PDF autenticado, edición y desactivación.
- Rechazo por HTTP de texto disfrazado de PDF y acceso anónimo al archivo.
- Recursos complementarios.
- Formulario de creación y desactivación de preguntas.
- Activación de cuestionario con doce preguntas válidas.
- Buscador y catálogo móvil.
- Visor PDF y apertura alternativa.
- Diez respuestas con guardado automático y navegación hacia atrás.
- Revisión previa y finalización.
- Nota 80/100 al acertar ocho preguntas en la prueba real.
- Corrección y explicaciones disponibles solo después de finalizar.
- Segundo intento e historial del estudiante.
- Resultado disponible para el administrador.
- Permisos PHP, CSRF y protección de configuración, SQL y credenciales.
- Sin errores JavaScript en esos flujos.

## Datos y limpieza

Las cuentas iniciales se prepararon por solicitud del usuario; las contraseñas se generaron aleatoriamente y se guardaron en `storage/accesos-iniciales.json`, fuera del DocumentRoot.

`tests/mysql.php` no crea tablas y revierte todos sus datos. `tests/wamp.cjs` crea una materia con marcador aleatorio único `SARU-E2E-...`, temas, materiales, preguntas e intentos de comprobación. Su limpieza elimina exclusivamente los registros asociados a esa materia y el PDF temporal. Los contenidos iniciales y las cuentas permanecen. La contraseña temporal del estudiante se restablece al valor de entrega al finalizar.

No se añadieron contenidos educativos ficticios permanentes ni se ejecutaron migraciones. La eliminación de registros sintéticos pertenece únicamente a las herramientas de prueba; no existe eliminación de historial en la API de la aplicación.

## Capturas

En `storage/wamp-tests/` se guardaron capturas del administrador, el banco de preguntas, el catálogo móvil, una pregunta, la revisión previa y el resultado final. Se inspeccionaron visualmente. Contienen identificadores de los registros sintéticos de prueba, que ya fueron eliminados.

## Repetir pruebas

Las pruebas de backend requieren las extensiones indicadas y la configuración local. Las pruebas de navegador usan Node y Playwright como herramientas, sin añadirlos como dependencias de SARU.

```powershell
& 'C:\wamp64\bin\php\php8.4.15\php.exe' -n -d 'extension_dir=C:/wamp64/bin/php/php8.4.15/ext' -d extension=pdo_sqlite C:\Saru\tests\backend.php
& 'C:\wamp64\bin\php\php8.4.15\php.exe' -n C:\Saru\tests\api.php
& 'C:\wamp64\bin\php\php8.4.15\php.exe' -n -d 'extension_dir=C:/wamp64/bin/php/php8.4.15/ext' -d extension=pdo_mysql -d extension=fileinfo C:\Saru\tests\mysql.php
```

`tests/wamp.cjs` requiere que `saru.com` esté activo, el archivo privado de accesos y Playwright disponible en el entorno de pruebas. No necesita paquetes instalados en el proyecto. Su URL puede cambiarse con `SARU_TEST_URL`; el navegador con `SARU_BROWSER` y PHP con `SARU_PHP`.

**La prueba Wamp restablece la contraseña del estudiante de entrega** para conservar sus credenciales iniciales; úsala únicamente durante la instalación y verificación inicial, antes de entregar esa cuenta a un estudiante real. Las otras pruebas usan usuarios temporales revertidos. Si una prueba de navegador se interrumpe abruptamente antes de su limpieza, su marcador se muestra en consola y `tests/limpiar-e2e.php MARCADOR` puede retirar exclusivamente los datos de ese marcador.

## Límites de esta verificación

Se comprobó el funcionamiento en este WampServer local y tamaños de escritorio/móvil en Chrome. No se realizó despliegue en hosting público, acceso desde un teléfono físico, pruebas de carga ni una auditoría externa de seguridad. La serialización utiliza bloqueos de MySQL y transacciones; no se ejecutó una prueba de estrés con muchos usuarios simultáneos.
