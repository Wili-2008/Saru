// Pruebas de interfaz con API simulada. No se conectan a MySQL ni crean cuentas reales.
// Requiere Playwright SOLO como herramienta de prueba, no como dependencia de SARU.
const { chromium } = require('playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const artifacts = path.join(root, 'storage', 'ui-tests');
let current = null;
let students = [];
let mode = 'admin';
const admin = { id: 1, nombres: 'Administrador', apellidos: 'Prueba', usuario: 'admin-prueba', rol: 'administrador', cambiar_password: false, activo: true };
const student = { id: 2, nombres: 'Lucía', apellidos: 'Prueba', usuario: 'lucia.prueba', rol: 'estudiante', cambiar_password: true, activo: true };
let checks = 0;
const ok = (label) => { checks++; console.log('OK ' + label); };

(async () => {
  await fs.mkdir(artifacts, { recursive: true });
  const context = await chromium.launchPersistentContext(path.join(artifacts, 'profile'), {
    headless: true, channel: process.env.SARU_BROWSER || 'chrome', viewport: { width: 1440, height: 1000 }, timeout: 20000,
  });
  try {
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await context.route('http://saru.test/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/api/') {
        const name = url.searchParams.get('route');
        const body = route.request().postDataJSON() || {};
        let data = {};
        if (name === 'sesion') data = { usuario: current, csrf: 'csrf-simulado' };
        else if (name === 'auth/login') { current = mode === 'admin' ? { ...admin } : { ...student }; data = { usuario: current, csrf: 'csrf-simulado' }; }
        else if (name === 'auth/logout') current = null;
        else if (name === 'auth/password') { current.cambiar_password = false; data = { usuario: current, csrf: 'csrf-nuevo' }; }
        else if (name === 'resumen') data = { total: students.length, activos: students.length, inactivos: 0, pendientes: students.length };
        else if (name === 'cursos') data = { cursos: [{ id: 1, nombre: 'Sexto de secundaria' }] };
        else if (name === 'catalogo') data = { materias: [], temas: [] };
        else if (name === 'instalacion') data = { disponible: false };
        else if (name === 'estudiantes') {
          if (route.request().method() === 'POST') {
            students.push({ ...body, id: 3, activo: 1, cambiar_password: 1, curso: 'Sexto de secundaria' });
            data = { id: 3 };
          } else data = { estudiantes: students };
        } else if (name.startsWith('estudiantes/')) {
          const s = students.find(s => s.id === Number(name.split('/')[1]));
          if (name.endsWith('/estado')) s.activo = Number(body.activo);
          if (name.endsWith('/datos')) Object.assign(s, body);
          if (name.endsWith('/password')) s.cambiar_password = 1;
        } else throw new Error('Ruta inesperada: ' + name);
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ estado: true, message: 'Operación simulada.', data }) });
        return;
      }
      const relative = decodeURIComponent(url.pathname).replace(/^\/+/, '');
      let file = path.resolve(root, 'frontend', relative);
      if (url.pathname.endsWith('/')) file = path.join(file, 'index.html');
      assert(file.startsWith(path.join(root, 'frontend') + path.sep));
      const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css', '.svg': 'image/svg+xml' };
      await route.fulfill({ status: 200, contentType: types[path.extname(file)], body: await fs.readFile(file) });
    });
    await page.goto('http://saru.test/');
    await page.getByRole('button', { name: 'Iniciar sesión' }).waitFor();
    await page.waitForFunction(() => !document.querySelector('[type=submit]').disabled);
    await page.screenshot({ path: path.join(artifacts, 'login-desktop.png'), fullPage: true });
    await page.getByLabel('Contraseña', { exact: true }).fill('Frase de prueba 123!');
    await page.getByRole('button', { name: 'Mostrar contraseña' }).click();
    assert.equal(await page.getByLabel('Contraseña', { exact: true }).getAttribute('type'), 'text');
    ok('Mostrar contraseña en acceso');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: path.join(artifacts, 'login-mobile.png'), fullPage: true });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    ok('Login móvil sin desbordamiento');
    await page.getByLabel('Usuario', { exact: true }).fill('admin-prueba');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.waitForURL('**/administrador/');
    await page.getByRole('heading', { name: 'Hola, Administrador' }).waitFor();
    ok('Login redirige a panel del administrador');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.screenshot({ path: path.join(artifacts, 'admin-desktop.png'), fullPage: true });
    await page.getByRole('link', { name: 'Estudiantes', exact: true }).click();
    await page.getByRole('heading', { name: 'Aquí comienza tu comunidad.' }).waitFor();
    await page.getByRole('button', { name: 'Nuevo estudiante' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nombres', { exact: true }).fill('Lucía');
    await dialog.getByLabel('Apellidos', { exact: true }).fill('Prueba');
    await dialog.getByLabel('Usuario', { exact: true }).fill('lucia.prueba');
    await dialog.getByLabel('Curso', { exact: true }).selectOption('1');
    await dialog.getByLabel('Contraseña temporal', { exact: true }).fill('Temporal de prueba 123!');
    await dialog.getByLabel('Confirmar contraseña temporal').fill('Temporal de prueba 123!');
    await dialog.getByRole('button', { name: 'Registrar estudiante' }).click();
    await dialog.waitFor({ state: 'hidden' });
    await page.getByText('lucia.prueba', { exact: true }).waitFor();
    ok('Registro de estudiante y recarga de lista');
    await page.getByRole('searchbox').fill('inexistente');
    await page.getByRole('heading', { name: 'No encontramos coincidencias.' }).waitFor();
    await page.getByRole('searchbox').fill('');
    ok('Filtro por nombre o usuario');
    await page.getByRole('button', { name: 'Editar a Lucía' }).click();
    await dialog.getByLabel('Nombres', { exact: true }).fill('Lucía Editada');
    await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
    await dialog.waitFor({ state: 'hidden' });
    await page.getByRole('button', { name: 'Editar a Lucía Editada' }).waitFor();
    ok('Edición de estudiante');
    await page.getByRole('button', { name: 'Desactivar', exact: true }).click();
    await dialog.getByRole('button', { name: 'Desactivar cuenta', exact: true }).click();
    await dialog.waitFor({ state: 'hidden' });
    await page.getByText('Inactivo', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Activar', exact: true }).click();
    await dialog.getByRole('button', { name: 'Activar cuenta', exact: true }).click();
    await dialog.waitFor({ state: 'hidden' });
    ok('Desactivación y activación con confirmación');
    await page.getByRole('button', { name: 'Restablecer contraseña de Lucía Editada' }).click();
    await dialog.getByLabel('Contraseña temporal', { exact: true }).fill('Temporal nueva 456!');
    await dialog.getByLabel('Confirmar contraseña temporal').fill('Temporal nueva 456!');
    await dialog.getByRole('button', { name: 'Restablecer contraseña', exact: true }).click();
    await dialog.waitFor({ state: 'hidden' });
    ok('Restablecimiento de contraseña');
    await page.screenshot({ path: path.join(artifacts, 'students-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    assert((await page.getByRole('searchbox').boundingBox()).width >= 120);
    await page.screenshot({ path: path.join(artifacts, 'students-mobile.png'), fullPage: true });
    ok('Gestión móvil con tabla desplazable');
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
    await page.waitForURL('http://saru.test/');
    ok('Cierre de sesión vuelve al acceso');
    mode = 'student';
    await page.waitForFunction(() => !document.querySelector('[type=submit]').disabled);
    await page.getByLabel('Usuario', { exact: true }).fill('lucia.prueba');
    await page.getByLabel('Contraseña', { exact: true }).fill('Temporal nueva 456!');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.waitForURL('**/cuenta/password.html');
    ok('Contraseña temporal redirige al cambio obligatorio');
    await page.getByLabel('Contraseña actual', { exact: true }).fill('Temporal nueva 456!');
    await page.getByLabel('Nueva contraseña', { exact: true }).fill('Personal de prueba 789!');
    await page.getByLabel('Confirmar nueva contraseña').fill('Personal de prueba 789!');
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await page.waitForURL('**/estudiante/');
    await page.getByRole('heading', { name: 'Hola, Lucía' }).waitFor();
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await page.screenshot({ path: path.join(artifacts, 'student-mobile.png'), fullPage: true });
    ok('Cambio de contraseña abre panel del estudiante');
    await page.goto('http://saru.test/administrador/');
    await page.waitForURL('**/estudiante/');
    ok('Navegación del estudiante no muestra panel administrativo');
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
    await page.waitForURL('http://saru.test/');
    await page.goto('http://saru.test/administrador/estudiantes.html');
    await page.waitForURL('http://saru.test/');
    ok('Sin sesión redirige al acceso');
    await page.goto('http://saru.test/instalacion.html');
    await page.getByText('La instalación está deshabilitada.', { exact: false }).waitFor();
    assert.equal(await page.locator('#install-form').isVisible(), false);
    ok('Instalador bloqueado oculta formulario');
    assert.deepEqual(errors, []);
    ok('Sin errores JavaScript durante los flujos');
    console.log(`TOTAL: ${checks} comprobaciones de interfaz con API simulada. Capturas en storage/ui-tests/.`);
  } finally { await context.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
