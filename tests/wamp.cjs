// Prueba real: navegador -> Apache -> PHP -> MySQL, con datos temporales identificados.
// Requiere las cuentas iniciales generadas y Playwright como herramienta de prueba.
const { chromium } = require('playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = process.env.SARU_TEST_URL || 'http://saru.com/';
const php = process.env.SARU_PHP || 'C:/wamp64/bin/php/php8.4.15/php.exe';
const marker = 'SARU-E2E-' + crypto.randomBytes(8).toString('hex');
const artifacts = path.join(root, 'storage', 'wamp-tests');
const apiUrl = route => new URL('api/?route=' + encodeURIComponent(route), base).href;
let checks = 0;
function ok(label) { checks++; console.log('OK ' + label); }
async function api(context, route, data, expected = 200) {
  let response;
  if (data === undefined) response = await context.request.get(apiUrl(route));
  else {
    const session = await (await context.request.get(apiUrl('sesion'))).json();
    response = await context.request.post(apiUrl(route), { data, headers: { 'X-CSRF-Token': session.data.csrf } });
  }
  assert.equal(response.status(), expected, `${route}: ${await response.text()}`);
  const result = await response.json(); assert.equal(result.estado, expected < 400); return result.data;
}
function pdfFixture() {
  let text = '%PDF-1.4\n'; const offsets = [0];
  const stream = 'BT /F1 16 Tf 50 750 Td (SARU - comprobacion de carga PDF) Tj ET';
  const objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`];
  objects.forEach((obj, i) => { offsets.push(Buffer.byteLength(text)); text += `${i + 1} 0 obj\n${obj}\nendobj\n`; });
  const start = Buffer.byteLength(text); text += 'xref\n0 6\n0000000000 65535 f \n';
  offsets.slice(1).forEach(offset => text += String(offset).padStart(10, '0') + ' 00000 n \n');
  text += `trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${start}\n%%EOF\n`; return Buffer.from(text);
}
async function fillLogin(page, credentials) {
  await page.goto(base); await page.waitForFunction(() => !document.querySelector('[type=submit]').disabled);
  await page.getByLabel('Usuario', { exact: true }).fill(credentials.usuario);
  await page.getByLabel('Contraseña', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
}
async function saveDialog(page) {
  const dialog = page.getByRole('dialog'); await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
  await dialog.waitFor({ state: 'hidden' });
}
function rowFor(page, title) { return page.locator('.record-row').filter({ has: page.getByRole('heading', { name: title, exact: true }) }); }
(async () => {
  await fs.mkdir(artifacts, { recursive: true });
  const access = JSON.parse(await fs.readFile(path.join(root, 'storage', 'accesos-iniciales.json'), 'utf8'));
  const context = await chromium.launchPersistentContext(path.join(artifacts, 'profile'), { headless: true, channel: process.env.SARU_BROWSER || 'chrome', viewport: { width: 1440, height: 1000 } });
  const studentContext = await context.browser().newContext({ viewport: { width: 390, height: 844 } });
  let studentId; let fixtureCreated = false;
  try {
    const page = await context.newPage(); const studentPage = await studentContext.newPage(); const errors = [];
    page.on('pageerror', e => errors.push(e.message)); studentPage.on('pageerror', e => errors.push(e.message));
    const health = await api(context, 'salud'); assert.equal(health.base_de_datos, 'dbsaru'); ok('HTTP real comunica Apache, PHP y MySQL');
    await fillLogin(page, access.administrador); await page.waitForURL('**/administrador/');
    await page.getByRole('heading', { name: 'Hola, Administrador' }).waitFor(); ok('Login real del administrador');
    await page.screenshot({ path: path.join(artifacts, 'administrador.png'), fullPage: true });
    const accounts = (await api(context, 'estudiantes')).estudiantes;
    studentId = Number(accounts.find(s => s.usuario === access.estudiante.usuario).id);
    await api(context, `estudiantes/${studentId}/password`, { password: access.estudiante.password });
    await page.getByRole('link', { name: 'Contenidos', exact: true }).click();
    await page.getByRole('button', { name: 'Agregar materia' }).click();
    let dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nombre', { exact: true }).fill(marker);
    const course = (await api(context, 'cursos')).cursos[0];
    await dialog.getByLabel('Curso', { exact: true }).selectOption(String(course.id));
    await saveDialog(page); fixtureCreated = true;
    const subject = (await api(context, 'admin/materias')).materias.find(m => m.nombre === marker);
    assert(subject); ok('CRUD de materias desde el navegador');
    await page.getByRole('link', { name: 'Temas', exact: true }).click();
    await page.getByRole('button', { name: 'Agregar tema' }).click(); dialog = page.getByRole('dialog');
    const topicTitle = 'Tema de comprobación ' + marker;
    await dialog.getByLabel('Título', { exact: true }).fill(topicTitle);
    await dialog.getByLabel('Materia', { exact: true }).selectOption(String(subject.id));
    await dialog.getByLabel('Descripción', { exact: true }).fill('Descripción para verificar la búsqueda ' + marker);
    await saveDialog(page);
    const topic = (await api(context, 'admin/temas')).temas.find(t => t.titulo === topicTitle);
    assert.equal(topic.estado, 'borrador'); ok('Crea temas en borrador');
    await fillLogin(studentPage, access.estudiante); await studentPage.waitForURL('**/cuenta/password.html');
    await studentPage.getByLabel('Contraseña actual', { exact: true }).fill(access.estudiante.password);
    const newPassword = crypto.randomBytes(16).toString('hex');
    await studentPage.getByLabel('Nueva contraseña', { exact: true }).fill(newPassword);
    await studentPage.getByLabel('Confirmar nueva contraseña').fill(newPassword);
    await studentPage.getByRole('button', { name: 'Guardar contraseña' }).click(); await studentPage.waitForURL('**/estudiante/');
    await studentPage.getByRole('heading', { name: 'Hola, Estudiante' }).waitFor(); ok('Estudiante cambia contraseña temporal y entra a su panel');
    await api(studentContext, `catalogo/temas/${topic.id}`, undefined, 404);
    const hidden = await api(studentContext, 'catalogo'); assert(!hidden.temas.some(t => Number(t.id) === Number(topic.id))); ok('Estudiante no accede a temas en borrador');
    await rowFor(page, topicTitle).getByRole('button', { name: 'Editar' }).click();
    await page.getByRole('dialog').getByLabel('Estado', { exact: true }).selectOption('publicado'); await saveDialog(page);
    assert((await api(studentContext, 'catalogo')).temas.some(t => Number(t.id) === Number(topic.id))); ok('Publicación habilita el tema para su curso');
    await page.getByRole('link', { name: 'Materiales PDF', exact: true }).click();
    await page.getByRole('button', { name: 'Agregar PDF' }).click(); dialog = page.getByRole('dialog');
    await dialog.getByLabel('Título', { exact: true }).fill('PDF de comprobación ' + marker);
    await dialog.getByLabel('Tema', { exact: true }).selectOption(String(topic.id));
    await dialog.getByLabel('Archivo PDF', { exact: true }).setInputFiles({ name: 'verificacion.pdf', mimeType: 'application/pdf', buffer: pdfFixture() });
    await saveDialog(page);
    const material = (await api(context, 'admin/materiales')).materiales.find(m => Number(m.tema_id) === Number(topic.id));
    assert(material && !material.nombre_guardado); ok('Carga PDF real y oculta el nombre físico');
    const csrfForUpload = (await api(context, 'sesion')).csrf;
    const invalidUpload = await context.request.post(apiUrl('admin/materiales'), { headers: { 'X-CSRF-Token': csrfForUpload }, multipart: {
      datos: JSON.stringify({ tema_id: Number(topic.id), titulo: 'Archivo inválido de prueba', tipo: 'resumen', activo: true }),
      archivo: { name: 'falso.pdf', mimeType: 'application/pdf', buffer: Buffer.from('Esto es texto, no un documento PDF.') },
    } });
    assert.equal(invalidUpload.status(), 422); assert.equal((await invalidUpload.json()).estado, false); ok('Carga HTTP rechaza archivos disfrazados de PDF');
    const pdfResponse = await studentContext.request.get(apiUrl(`materiales/${material.id}/archivo`));
    assert.equal(pdfResponse.status(), 200); assert(pdfResponse.headers()['content-type'].includes('application/pdf'));
    assert((await pdfResponse.body()).subarray(0, 5).toString() === '%PDF-'); ok('Entrega PDF autenticado al estudiante');
    await rowFor(page, material.titulo).getByRole('button', { name: 'Editar' }).click();
    await page.getByRole('dialog').getByLabel('Cuenta o contenido activo').uncheck(); await saveDialog(page);
    assert.equal((await studentContext.request.get(apiUrl(`materiales/${material.id}/archivo`))).status(), 404);
    await rowFor(page, material.titulo).getByRole('button', { name: 'Editar' }).click();
    await page.getByRole('dialog').getByLabel('Cuenta o contenido activo').check(); await saveDialog(page); ok('Edición y desactivación de PDF protegidas por el servidor');
    const anonymous = await context.browser().newContext();
    assert.equal((await anonymous.request.get(apiUrl(`materiales/${material.id}/archivo`))).status(), 401); await anonymous.close(); ok('PDF sin sesión denegado');
    await page.getByRole('link', { name: 'Enlaces y recursos', exact: true }).click();
    await page.getByRole('button', { name: 'Agregar recurso' }).click(); dialog = page.getByRole('dialog');
    await dialog.getByLabel('Título', { exact: true }).fill('Enlace de comprobación ' + marker);
    await dialog.getByLabel('Tema', { exact: true }).selectOption(String(topic.id));
    await dialog.getByLabel('URL (HTTP o HTTPS)', { exact: true }).fill('https://example.com/'); await saveDialog(page);
    assert.equal((await api(studentContext, `catalogo/temas/${topic.id}`)).recursos.length, 1); ok('Recursos complementarios disponibles');
    await page.getByRole('link', { name: 'Cuestionarios', exact: true }).click();
    await page.getByRole('button', { name: 'Nuevo cuestionario' }).click(); dialog = page.getByRole('dialog');
    await dialog.getByLabel('Título', { exact: true }).fill('Evaluación de comprobación ' + marker);
    await dialog.getByLabel('Tema', { exact: true }).selectOption(String(topic.id)); await saveDialog(page);
    const quiz = (await api(context, 'admin/cuestionarios')).cuestionarios.find(q => Number(q.tema_id) === Number(topic.id));
    await api(context, `admin/cuestionarios/${quiz.id}`, { titulo: quiz.titulo, activo: true }, 422); ok('API impide activar una evaluación vacía');
    for (let i = 1; i <= 12; i++) await api(context, `admin/cuestionarios/${quiz.id}/preguntas`, {
      enunciado: `Pregunta de verificación ${i} · ${marker}`, explicacion: `Explicación de verificación ${i}.`, activo: true,
      opciones: [{ texto: `Valor correcto ${i}`, es_correcta: true }, ...[1, 2, 3].map(n => ({ texto: `Alternativa ${n} de ${i}`, es_correcta: false }))],
    }, 201);
    await page.goto(new URL(`administrador/preguntas.html?id=${quiz.id}`, base).href);
    await page.getByRole('button', { name: 'Nueva pregunta' }).waitFor();
    assert.equal(await page.locator('.record-row').count(), 12); ok('Banco de preguntas comunica la API real');
    await page.getByRole('button', { name: 'Nueva pregunta' }).click(); dialog = page.getByRole('dialog');
    const uiQuestionTitle = 'Pregunta creada desde el formulario ' + marker;
    await dialog.getByLabel('Enunciado', { exact: true }).fill(uiQuestionTitle);
    for (let n = 1; n <= 4; n++) await dialog.getByLabel(`Opción ${n}`, { exact: true }).fill('Alternativa de formulario ' + n);
    await dialog.getByRole('radio', { name: 'Opción 2 correcta' }).check();
    await dialog.getByLabel('Explicación (se muestra después de finalizar)', { exact: true }).fill('Explicación de verificación del formulario.');
    await saveDialog(page); assert.equal(await page.locator('.record-row').count(), 13); ok('Crea preguntas con cuatro alternativas desde el formulario');
    await rowFor(page, uiQuestionTitle).getByRole('button', { name: 'Editar' }).click();
    await page.getByRole('dialog').getByLabel('Cuenta o contenido activo').uncheck(); await saveDialog(page); ok('Edita y desactiva preguntas desde el formulario');
    await page.screenshot({ path: path.join(artifacts, 'preguntas.png'), fullPage: true });
    await page.getByRole('link', { name: 'Todos los cuestionarios', exact: false }).click();
    const quizCard = page.locator('.topic-card').filter({ has: page.getByRole('heading', { name: quiz.titulo, exact: true }) });
    await quizCard.getByRole('button', { name: 'Editar' }).click();
    await page.getByRole('dialog').getByLabel('Cuenta o contenido activo').check(); await saveDialog(page); ok('Activa cuestionario con 12 preguntas válidas');
    await studentPage.reload(); await studentPage.getByRole('heading', { name: 'Hola, Estudiante' }).waitFor();
    await studentPage.getByRole('searchbox').fill(marker); await studentPage.getByRole('button', { name: 'Buscar', exact: true }).click();
    await studentPage.getByRole('heading', { name: topicTitle, exact: true }).waitFor();
    assert(await studentPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); ok('Buscador y catálogo móvil reales');
    await studentPage.screenshot({ path: path.join(artifacts, 'catalogo-movil.png'), fullPage: true });
    await studentPage.getByRole('link').filter({ has: studentPage.getByRole('heading', { name: topicTitle, exact: true }) }).click();
    await studentPage.getByRole('link', { name: 'Leer PDF' }).click();
    await studentPage.getByRole('link', { name: 'Abrir PDF en otra pestaña' }).waitFor();
    assert((await studentPage.locator('iframe').getAttribute('src')).includes('materiales')); ok('Visor PDF con alternativa de apertura');
    await studentPage.getByRole('link', { name: 'Volver al tema', exact: false }).click();
    await studentPage.getByRole('button', { name: 'Comenzar o retomar' }).click();
    await studentPage.waitForURL('**/evaluacion.html?id=*');
    const attemptId = Number(new URL(studentPage.url()).searchParams.get('id'));
    const attempt = await api(studentContext, `intentos/${attemptId}`);
    assert.equal(attempt.preguntas.length, 10);
    assert(attempt.preguntas.every(p => !('explicacion' in p) && p.opciones.length === 4 && p.opciones.every(o => !('es_correcta' in o)))); ok('No se filtran claves correctas ni explicaciones antes de finalizar');
    for (let i = 0; i < 10; i++) {
      const p = attempt.preguntas[i]; const chosen = p.opciones.find(o => i < 8 ? o.texto.startsWith('Valor correcto') : o.texto.startsWith('Alternativa'));
      const saved = studentPage.waitForResponse(r => new URL(r.url()).searchParams.get('route') === `intentos/${attemptId}/respuesta` && r.request().method() === 'POST');
      await studentPage.locator(`[name=answer][value="${chosen.id}"]`).check(); assert((await saved).ok());
      await studentPage.getByText('✓ Respuesta guardada. Puedes cambiarla.', { exact: true }).waitFor();
      if (i === 0) await studentPage.screenshot({ path: path.join(artifacts, 'evaluacion-movil.png'), fullPage: true });
      await studentPage.getByRole('button', { name: i === 9 ? 'Revisar respuestas' : 'Siguiente' }).click();
    }
    await studentPage.getByRole('heading', { name: 'Revisa tus respuestas' }).waitFor(); ok('Responde diez preguntas una por una y muestra revisión previa');
    assert(await studentPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await studentPage.screenshot({ path: path.join(artifacts, 'revision-movil.png'), fullPage: true });
    await studentPage.locator('[data-jump="2"]').click();
    await studentPage.locator('[data-jump="9"]').click(); await studentPage.getByRole('button', { name: 'Revisar respuestas' }).click(); ok('Navega hacia atrás desde la revisión sin perder respuestas');
    await studentPage.getByRole('button', { name: 'Finalizar evaluación' }).click();
    await studentPage.getByRole('heading', { name: 'Un paso más en tu aprendizaje.' }).waitFor();
    const final = await api(studentContext, `intentos/${attemptId}`); assert.equal(Number(final.intento.calificacion), 80); assert.equal(Number(final.intento.aciertos), 8);
    assert(final.preguntas.every(p => 'explicacion' in p && p.opciones.every(o => 'es_correcta' in o))); ok('Califica 80/100 y muestra revisión completa');
    await studentPage.screenshot({ path: path.join(artifacts, 'resultado-movil.png'), fullPage: true });
    assert.equal(Number((await api(studentContext, `intentos/${attemptId}/finalizar`, {})).calificacion), 80); ok('Doble finalización mantiene la misma nota');
    const repeated = await api(studentContext, 'intentos', { cuestionario_id: Number(quiz.id) }, 201);
    assert.notEqual(Number(repeated.id), attemptId);
    assert.equal((await api(studentContext, 'historial')).intentos.filter(i => Number(i.cuestionario_id) === Number(quiz.id)).length, 2); ok('Repetición e historial conservan dos intentos');
    await page.getByRole('link', { name: 'Resultados', exact: true }).click();
    await page.getByRole('searchbox').fill(marker); await page.getByText('80/100', { exact: true }).waitFor(); ok('Administrador consulta el resultado real');
    await api(studentContext, 'admin/resultados', undefined, 403); ok('Permisos administrativos se aplican en PHP');
    const noCsrf = await context.request.post(apiUrl('admin/materias'), { data: { nombre: 'rechazar' } }); assert.equal(noCsrf.status(), 403); ok('Operaciones sin CSRF rechazadas');
    for (const file of ['backend/config/local.php', 'storage/accesos-iniciales.json', 'database/dbsaru.sql']) {
      const response = await context.request.get(new URL(file, base).href); assert([403, 404].includes(response.status()));
    } ok('Configuración, SQL y credenciales no se publican');
    await studentPage.getByRole('button', { name: 'Cerrar sesión' }).click(); await studentPage.waitForURL(base);
    assert.equal((await api(studentContext, 'sesion')).usuario, null); ok('Cierre de sesión real');
    assert.deepEqual(errors, []); ok('Sin errores JavaScript en los flujos completos');
    console.log(`TOTAL: ${checks} comprobaciones reales en WampServer.`);
  } finally {
    // Restituye la contraseña temporal entregada, sin cambiar la cuenta del usuario.
    if (studentId) {
      try { await api(context, `estudiantes/${studentId}/password`, { password: access.estudiante.password }); console.log('Contraseña temporal inicial del estudiante restaurada.'); }
      catch (e) { console.error('Revisar contraseña temporal del estudiante:', e.message); process.exitCode = 1; }
    }
    await studentContext.close(); await context.close();
    if (fixtureCreated) {
      const clean = spawnSync(php, ['-n', '-d', 'extension_dir=' + path.dirname(php) + '/ext', '-d', 'extension=pdo_mysql', path.join(root, 'tests', 'limpiar-e2e.php'), marker], { encoding: 'utf8' });
      console.log(clean.stdout.trim());
      if (clean.status !== 0) { console.error('Datos temporales pendientes:', marker, clean.stderr); process.exitCode = 1; }
    }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
