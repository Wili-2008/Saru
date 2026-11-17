# SARU · Sistema de Aprendizaje Rápido

Aplicación educativa para sexto de secundaria. Frontend HTML5, CSS3 y JavaScript nativo; API PHP con sesiones y PDO; base MySQL `dbsaru`. Funciona con WampServer, sin frameworks ni dependencias de aplicación.

El sistema incluye autenticación, estudiantes, materias, temas, PDF, recursos, banco de preguntas, evaluaciones, revisión, calificaciones e historial. La estructura de las 13 tablas suministradas se mantiene sin migraciones.

## Acceder ahora

1. Mantén WampServer iniciado (Apache y MySQL).
2. Abre **http://saru.com/**.
3. Usa el administrador o el estudiante cuyas credenciales se entregaron en el chat. También están en el archivo local privado `C:\Saru\storage\accesos-iniciales.json`.
4. El estudiante debe cambiar su contraseña temporal en el primer ingreso. El administrador puede cambiar la suya desde **Mi contraseña**.

No hay registro público ni contraseñas predeterminadas en el código. Las dos cuentas iniciales se crearon por solicitud del usuario mediante una herramienta de consola con contraseñas aleatorias. El instalador quedó bloqueado después de crear al primer administrador.

**Los tres temas originales siguen en borrador.** Los PDF, enlaces y preguntas reales deben ser cargados desde el administrador; no se agregaron contenidos educativos ficticios. Los registros sintéticos usados en las pruebas fueron eliminados o revertidos. Mientras no publiques temas, el estudiante verá el catálogo vacío.

La guía paso a paso para ambos roles está en `docs/GUIA_USO.md`.

## Organización

```text
frontend/
  index.html                        Login
  instalacion.html                  Instalación del primer administrador
  administrador/
    index.html                      Resumen y accesos rápidos
    estudiantes.html                Cuentas estudiantiles
    contenidos.html                 Materias, temas, PDF y recursos
    cuestionarios.html              Cuestionarios por tema
    preguntas.html                  Banco de preguntas
    resultados.html                 Intentos y calificaciones generales
  estudiante/
    index.html                      Materias y buscador de temas
    tema.html                       Materiales, enlaces y cuestionario
    visor.html                      PDF autenticado y apertura alternativa
    evaluacion.html                 Preguntas, revisión y resultado
    historial.html                  Reanudar o revisar intentos
  cuenta/password.html              Cambio de contraseña
  css/saru.css                      Diseño responsive
  js/                               Módulos de cada pantalla y API centralizada
  assets/                           Identidad visual local
backend/
  api/index.php                     Entrada JSON y archivos autenticados
  config/                           PDO, configuración local y plantilla
  controllers/                      Auth, instalación, estudiantes y aprendizaje
  middleware/Auth.php               Sesiones, CSRF y roles
  models/Usuario.php                 Datos y consultas de usuarios
  services/                         Validación, transacciones, rutas y límites
storage/                            Archivos privados fuera del DocumentRoot
  materiales/                       PDF con nombres aleatorios
  accesos-iniciales.json             Credenciales iniciales privadas, no versionadas
  installed.lock                    Bloqueo permanente del instalador
  wamp-tests/                       Capturas de pruebas reales
  ui-tests/                         Capturas de pruebas con API simulada
database/dbsaru.sql                  Copia exacta del SQL suministrado
bdsaru.sql                          SQL original conservado
docs/                               Configuración, uso, API y pruebas
tools/                              Herramientas locales de instalación
tests/                              Pruebas aisladas, MySQL y navegador
```

## Configuración local realizada

- `backend/config/local.php` contiene los datos de conexión confirmados por el usuario. Está excluido de Git y fuera del frontend.
- El VirtualHost de `saru.com` apunta a `C:/Saru/frontend`.
- `/api/` está publicado mediante Alias a `C:/Saru/backend/api/`.
- Se conservó el resto de VirtualHosts del equipo. La copia previa está en `storage/httpd-vhosts.before-saru.conf`.
- Apache se reinició desde WampServer y se comprobó el acceso HTTP real.
- Las credenciales iniciales se almacenaron únicamente en el archivo privado indicado y en sus hashes en MySQL.

El archivo `docs/saru-vhost.conf` conserva el bloque exacto para reinstalar el VirtualHost. `tools/configurar-wamp.ps1` reemplaza solo el bloque de `saru.com` y comprueba su sintaxis; después hay que reiniciar Apache desde WampServer. No reemplaces el archivo completo de VirtualHosts con ese bloque.

### Instalar en otro equipo o recuperar la configuración

1. Copia el proyecto y prepara WampServer con PHP 8.0+ y MySQL 8 o MariaDB compatible. Habilita `pdo_mysql` y `fileinfo`.
2. Si la base ya existe, **no vuelvas a ejecutar el SQL sobre ella**. Para una instalación realmente nueva, importa el SQL suministrado con Navicat; no hay migraciones automáticas.
3. Copia `backend/config/local.example.php` como `local.php` y completa las credenciales reales de `dbsaru` únicamente allí. No publiques ese archivo. Las variables `SARU_DB_HOST`, `SARU_DB_PORT`, `SARU_DB_NAME`, `SARU_DB_USER` y `SARU_DB_PASSWORD` tienen prioridad sobre la configuración local.
4. Configura el VirtualHost usando `docs/saru-vhost.conf`. El archivo hosts debe resolver `saru.com` a `127.0.0.1` y, si usas IPv6 local, a `::1`.
5. Reinicia Apache y abre `http://saru.com/api/?route=salud`: debe confirmar PHP y MySQL disponibles.
6. En una instalación nueva sin administrador, usa el instalador inicial descrito a continuación.

La base se llama siempre `dbsaru`. La aplicación nunca ejecuta CREATE, ALTER, DROP ni semillas automáticas. La configuración `Require local` limita el sitio a este equipo. El diseño móvil se puede comprobar con las herramientas del navegador; usar otro dispositivo exige configurar expresamente el acceso de red y la resolución del nombre.

### Instalador inicial

Solo se habilita localmente si no existe administrador ni bloqueo previo:

```powershell
& 'C:\wamp64\bin\php\php8.4.15\php.exe' C:\Saru\tools\habilitar-instalacion.php
```

La consola genera una clave privada con caducidad de una hora. Abre `http://saru.com/instalacion.html`, ingresa esa clave, los datos y una contraseña propia. Al crear el administrador se consume la clave y se escribe `storage/installed.lock`. Tanto el bloqueo como la existencia de un administrador impiden repetir la instalación. La creación está serializada con un bloqueo MySQL.

La herramienta `tools/crear-accesos.php` se usó para la preparación inicial solicitada. Solo funciona por consola, recibe los nombres de usuario como argumentos, genera contraseñas aleatorias y respeta el bloqueo de instalación. No crea administradores adicionales ni restablece cuentas existentes. Para estudiantes posteriores, usa el panel administrativo.

## Límites de archivos y PHP

- `pdf_max_bytes` en `local.php` establece el tamaño máximo de SARU; el valor predeterminado es 20 MB.
- También se aplican `upload_max_filesize` y `post_max_size` de PHP. Para admitir 20 MB, configura por ejemplo `upload_max_filesize = 20M` y `post_max_size = 25M` en el PHP usado por Apache, y reinicia WampServer.
- PHP necesita escribir en `storage/`, `storage/materiales/` y su directorio de sesiones.
- Se comprueban extensión `.pdf`, MIME real, firma `%PDF-` y tamaño. El nombre físico es aleatorio y nunca se devuelve al estudiante.
- Al reemplazar un PDF cambia el archivo servido por su identificador. La versión anterior se conserva fuera del acceso web; no existe una interfaz de versiones en esta entrega. Incluye `storage/` junto con MySQL en tus copias de respaldo.

## Comportamiento de las evaluaciones

- Un cuestionario por tema; se crea inactivo.
- Cada pregunta requiere exactamente cuatro opciones no vacías y una correcta.
- Para activar se necesitan al menos diez preguntas activas válidas. Se recomiendan 15–20 para variar más los intentos.
- Si una edición deja menos de diez válidas, se desactiva automáticamente. Agregar nuevas preguntas no lo reactiva hasta que lo indique el administrador.
- El servidor selecciona diez preguntas distintas y mezcla su orden y el de las alternativas.
- El intento copia enunciados, explicaciones y opciones dentro de una transacción. Cambiar después el banco no altera su revisión histórica.
- Antes de finalizar, la API no envía claves correctas ni explicaciones.
- Las respuestas se guardan al elegirlas. Se puede avanzar, retroceder, revisar y retomar desde el historial.
- Solo se finaliza con diez respuestas. La nota es `aciertos × 10`, entre 0 y 100. Finalizar dos veces no duplica ni cambia la calificación.
- No hay tiempo límite, nota mínima de aprobación ni límite de intentos. Un intento en progreso del mismo cuestionario se retoma; después de terminar se puede empezar otro.
- Una evaluación finalizada ya no admite cambios. Las copias propias del historial permanecen consultables aunque el contenido de origen se retire del catálogo.

## Seguridad y permisos

Las contraseñas se guardan con `password_hash()` y se verifican con `password_verify()`. Mínimo: 12 caracteres; máximo: 72 bytes. Las sesiones regeneran su identificador al iniciar sesión y al cambiar contraseña. Las cookies usan HttpOnly y SameSite=Strict, además de Secure bajo HTTPS.

Cada endpoint comprueba en el servidor la cuenta activa, su rol y la propiedad de sus intentos. La identidad proviene de la sesión, nunca de un usuario_id enviado por el navegador. El estudiante solo accede a materias activas de su curso y temas publicados. Los PDF se sirven tras verificar sesión, estado y acceso al tema. Los administradores pueden previsualizar sus PDF aunque el tema esté en borrador.

Todas las operaciones POST requieren CSRF. El frontend no guarda credenciales ni tokens de sesión en localStorage. Un restablecimiento de contraseña invalida las sesiones previas y obliga al cambio de la contraseña temporal. La sesión expira tras 30 minutos de inactividad, configurable con `session_minutes` entre 5 y 240 minutos.

Los intentos de login, instalación y cambio de contraseña se limitan durante ventanas de 15 minutos. El límite de login es compartido por IP y contabiliza también accesos correctos (30 por ventana); el instalador admite 10 y el cambio de contraseña 15 por cuenta e IP. Al excederlo se responde 429 con Retry-After.

No se eliminan cuentas ni historial desde el panel. Las materias, recursos, materiales y preguntas se desactivan; los temas se retiran a borrador. Las opciones de origen se reemplazan al editar preguntas, sin tocar las copias del historial. Las consultas usan PDO preparado y los errores SQL no se muestran al cliente.

## API

El punto de entrada es `/api/?route=RUTA`. GET consulta; POST modifica y recibe JSON, excepto PDF, que usa multipart con `datos` en JSON y `archivo`. El cliente central está en `frontend/js/api.js`; no tiene un dominio fijo ni necesita CORS.

Las respuestas JSON siguen `{estado, message, data}`. La entrega de un PDF autorizado devuelve `application/pdf`. Consulta el catálogo de rutas y permisos en `docs/API.md`.

## Pruebas realizadas

- **39 comprobaciones aisladas** con SQLite en memoria: autenticación, instalación única, CSRF, contraseñas, sesiones y estudiantes.
- **5 comprobaciones de entrada API** en PHP CLI: JSON, sesión, métodos, rutas y CSRF.
- **42 comprobaciones sobre MySQL real**, dentro de una transacción finalmente revertida: catálogo, permisos, validación de preguntas, selección de diez distintas, respuestas, calificación, repetición e inmutabilidad del historial.
- **16 comprobaciones de interfaz con API simulada**, en Chrome: autenticación y gestión de estudiantes, escritorio y móvil.
- **32 comprobaciones de extremo a extremo en WampServer**, con navegador real en `saru.com`: login de ambos roles, cambio temporal, materias, borradores y publicación, carga y desactivación de PDF, rechazo de PDF falso, visor, recursos, formularios de preguntas, cuestionarios, evaluaciones, revisión, nota 80/100, historial, resultados y permisos.
- Sintaxis PHP y JavaScript revisada. Configuración de Apache: `Syntax OK`. Capturas de escritorio y móvil inspeccionadas visualmente.

Las pruebas E2E crearon una materia con marcador aleatorio `SARU-E2E-...` y contenido sintético de verificación; al terminar se eliminaron exclusivamente esos registros y su PDF. La contraseña inicial del estudiante se restableció como temporal. Los tres temas suministrados, las cuentas y el esquema se conservaron. No se dejaron cuestionarios ni materiales ficticios.

El detalle de validación está en `docs/PRUEBAS.md`. Las pruebas de navegador utilizan Node/Playwright solo como herramientas de desarrollo; no se instalaron dependencias en la aplicación.

## Problemas habituales

- **403 o aparece la carpeta raíz:** comprobar que el VirtualHost apunta a `C:/Saru/frontend` y reiniciar Apache. La raíz del proyecto bloquea acceso directo como medida de protección.
- **La API devuelve HTML/404:** comprobar Alias `/api/`, usar `saru.com` y reiniciar Apache. No abrir con `file://` ni con Live Server.
- **503:** comprobar MySQL, `local.php`, `pdo_mysql`, los campos del SQL y los permisos de escritura. Ver el registro PHP de WampServer para el detalle interno.
- **No aparecen temas:** comprobar curso del estudiante, materia activa y tema publicado. Los temas iniciales están en borrador.
- **No aparece el cuestionario:** comprobar estado activo y diez preguntas válidas. El estudiante también necesita acceso al tema.
- **PDF no disponible:** comprobar que el material está activo, que su tema está publicado y que el archivo existe en `storage/materiales/`.
- **La carga falla:** comprobar `fileinfo`, `pdf_max_bytes`, `upload_max_filesize`, `post_max_size` y permisos de la carpeta.
- **CSRF expirado:** recargar el formulario.
- **429:** esperar el tiempo indicado por Retry-After.
- **Perdiste la contraseña de un estudiante:** restablecerla desde el administrador. No se puede recuperar una contraseña anterior a partir del hash.
