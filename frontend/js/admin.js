import { dashboard } from './admin-dashboard.js';
import { guard, request, site } from './api.js';
import { shell, esc, notice, pageError, verifyPasswords } from './ui.js';

let students = [];
let courses = [];
let user;
const isActive = student => Number(student.activo) === 1;
const mustChange = student => Number(student.cambiar_password) === 1;
const heading = (title, description, action = '') => `<div class="page-heading"><div><p class="eyebrow">ADMINISTRACIÓN</p><h1>${title}</h1><p class="muted">${description}</p></div>${action}</div>`;
const status = student => `<span class="status ${isActive(student) ? 'active' : 'inactive'}">${isActive(student) ? 'Activo' : 'Inactivo'}</span>`;

function studentRows() {
  const query = document.querySelector('#search').value.trim().toLocaleLowerCase('es');
  const state = document.querySelector('#state-filter').value;
  const filtered = students.filter(s => `${s.nombres} ${s.apellidos} ${s.usuario}`.toLocaleLowerCase('es').includes(query) && (state === '' || Number(s.activo) === Number(state)));
  document.querySelector('#student-count').textContent = `${filtered.length} de ${students.length} estudiantes`;
  document.querySelector('#student-body').innerHTML = filtered.length ? filtered.map(s => `<tr><td><div class="table-person"><span class="avatar soft-avatar">${esc(s.nombres.slice(0, 1))}</span><span><strong>${esc(s.nombres)} ${esc(s.apellidos)}</strong><small>${esc(s.curso || 'Sin curso asignado')}</small></span></div></td><td><span class="username">${esc(s.usuario)}</span></td><td>${status(s)}</td><td><span class="password-status">${mustChange(s) ? 'Cambio pendiente' : 'Personalizada'}</span></td><td><div class="row-actions"><button class="small-button" data-edit="${s.id}" type="button" aria-label="Editar a ${esc(s.nombres)}">Editar</button><button class="small-button" data-reset="${s.id}" type="button" aria-label="Restablecer contraseña de ${esc(s.nombres)}">Contraseña</button><button class="small-button ${isActive(s) ? 'danger-text' : ''}" data-toggle="${s.id}" type="button">${isActive(s) ? 'Desactivar' : 'Activar'}</button></div></td></tr>`).join('') : `<tr><td colspan="5"><div class="empty-state"><span>♧</span><h3>${students.length ? 'No encontramos coincidencias.' : 'Aquí comienza tu comunidad.'}</h3><p>${students.length ? 'Prueba con otro nombre, usuario o estado.' : 'Registra al primer estudiante para comenzar.'}</p></div></td></tr>`;
}

async function refresh() {
  const data = await request('estudiantes'); students = data.estudiantes; studentRows();
}

function courseOptions(selected) {
  return `<option value="">Selecciona un curso</option>${courses.map(c => `<option value="${c.id}" ${Number(c.id) === Number(selected) ? 'selected' : ''}>${esc(c.nombre)}</option>`).join('')}`;
}

function openDialog(title, description, fields, submitLabel, onSubmit) {
  const dialog = document.querySelector('#student-dialog');
  dialog.innerHTML = `<div class="dialog-heading"><div><span class="section-tag">ESTUDIANTES</span><h2>${title}</h2></div><button type="button" class="dialog-close" aria-label="Cerrar">×</button></div><p class="muted">${description}</p><div id="dialog-notice" role="status" aria-live="polite"></div><form class="form-stack">${fields}<div class="dialog-actions"><button type="button" class="button secondary cancel">Cancelar</button><button type="submit" class="button primary">${submitLabel}</button></div></form>`;
  const form = dialog.querySelector('form');
  const close = () => { dialog.close(); form.reset(); };
  dialog.querySelector('.dialog-close').onclick = close;
  dialog.querySelector('.cancel').onclick = close;
  dialog.showModal();
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = form.querySelector('[type=submit]'); submit.disabled = true;
    const target = dialog.querySelector('#dialog-notice'); notice('', 'error', target);
    try {
      const message = await onSubmit(form);
      close();
      // Una operación guardada no se vuelve a enviar si falla la recarga de la lista.
      try { await refresh(); notice(message, 'success'); }
      catch (error) { notice(`${message} No se pudo actualizar la lista: ${error.message}`); }
    } catch (error) {
      if (error.status === 401) { pageError(error); return; }
      notice(error.message, 'error', target); submit.disabled = false;
    }
  });
}

const passwordFields = `<label>Contraseña temporal<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><small class="muted">Mínimo 12 caracteres, máximo 72 bytes. Entrégala de forma privada. El estudiante deberá cambiarla al ingresar.</small><label>Confirmar contraseña temporal<input name="confirmar" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>`;

function edit(student) {
  openDialog(student ? 'Editar estudiante' : 'Nuevo estudiante', student ? 'Actualiza sus datos de acceso y su curso.' : 'Crea una cuenta para dar el primer paso.', `<div class="form-grid"><label>Nombres<input name="nombres" required maxlength="100" value="${esc(student?.nombres)}" autocomplete="off"></label><label>Apellidos<input name="apellidos" required maxlength="100" value="${esc(student?.apellidos)}" autocomplete="off"></label></div><label>Usuario<input name="usuario" required minlength="3" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" value="${esc(student?.usuario)}" autocomplete="off" autocapitalize="none" spellcheck="false"></label><small class="muted">Letras, números, punto, guion y guion bajo. El usuario es único.</small><label>Curso<select name="curso_id" required aria-label="Curso">${courseOptions(student?.curso_id)}</select></label>${student ? '' : passwordFields}`, student ? 'Guardar cambios' : 'Registrar estudiante', async form => {
    if (!student) verifyPasswords(form);
    const values = Object.fromEntries(new FormData(form)); values.curso_id = Number(values.curso_id);
    await request(student ? `estudiantes/${student.id}/datos` : 'estudiantes', values);
    return student ? 'Datos actualizados.' : 'Estudiante registrado. Entrega sus credenciales de forma privada.';
  });
}

async function management() {
  const results = await Promise.all([request('estudiantes'), request('cursos')]);
  students = results[0].estudiantes; courses = results[1].cursos;
  shell(user, 'estudiantes', `${heading('Estudiantes', 'Gestiona las cuentas que forman parte de tu comunidad.', '<button class="button primary" id="add-student" type="button"><span>+</span> Nuevo estudiante</button>')}<section class="panel students-panel"><div class="list-toolbar"><div><h2>Tu comunidad</h2><p id="student-count" class="muted"></p></div><div class="filters"><label class="search-label"><span class="sr-only">Buscar por nombre o usuario</span><input id="search" type="search" placeholder="Buscar nombre o usuario…" autocomplete="off"></label><label><span class="sr-only">Filtrar por estado</span><select id="state-filter"><option value="">Todos los estados</option><option value="1">Activos</option><option value="0">Inactivos</option></select></label></div></div><div class="table-scroll" tabindex="0" role="region" aria-label="Lista de estudiantes"><table><thead><tr><th>Estudiante</th><th>Usuario</th><th>Estado</th><th>Contraseña</th><th>Acciones</th></tr></thead><tbody id="student-body"></tbody></table></div></section><p class="table-footnote">Las cuentas desactivadas conservan sus datos y pierden el acceso al sistema.</p><dialog id="student-dialog" aria-label="Gestionar estudiante"></dialog>`);
  studentRows();
  document.querySelector('#search').addEventListener('input', studentRows);
  document.querySelector('#state-filter').addEventListener('change', studentRows);
  document.querySelector('#add-student').onclick = () => edit();
  document.querySelector('#student-body').addEventListener('click', event => {
    const button = event.target.closest('button'); if (!button) return;
    const id = Number(button.dataset.edit || button.dataset.reset || button.dataset.toggle);
    const student = students.find(s => Number(s.id) === id); if (!student) return;
    if (button.dataset.edit) { edit(student); return; }
    if (button.dataset.reset) {
      openDialog('Restablecer contraseña', `Define una contraseña temporal para ${esc(student.nombres)} ${esc(student.apellidos)}. Se cerrarán sus sesiones actuales.`, passwordFields, 'Restablecer contraseña', async form => {
        verifyPasswords(form); await request(`estudiantes/${id}/password`, Object.fromEntries(new FormData(form)));
        return 'Contraseña restablecida. El estudiante deberá cambiarla en su próximo ingreso.';
      });
      return;
    }
    openDialog(isActive(student) ? 'Desactivar cuenta' : 'Activar cuenta', `${esc(student.nombres)} ${esc(student.apellidos)} ${isActive(student) ? 'perderá el acceso a SARU. Sus datos se conservarán.' : 'podrá volver a iniciar sesión en SARU.'}`, '', isActive(student) ? 'Desactivar cuenta' : 'Activar cuenta', async () => {
      await request(`estudiantes/${id}/estado`, { activo: !isActive(student) });
      return 'Estado de la cuenta actualizado.';
    });
  });
}

async function start() {
  user = await guard('administrador'); if (!user) return;
  if (document.body.dataset.page === 'estudiantes') await management(); else await dashboard(user);
}
start().catch(pageError);
