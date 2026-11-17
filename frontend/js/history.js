import { guard, request, site } from './api.js';
import { shell, esc, pageError } from './ui.js';
import { heading, empty, date } from './learning-ui.js';

let attempts = [];
const admin = document.body.dataset.page === 'resultados';
function draw() {
  const query = document.querySelector('#history-search').value.toLocaleLowerCase('es');
  const state = document.querySelector('#history-state').value;
  const items = attempts.filter(a => `${a.nombres} ${a.apellidos} ${a.usuario} ${a.tema} ${a.materia}`.toLocaleLowerCase('es').includes(query) && (!state || a.estado === state));
  document.querySelector('#history-body').innerHTML = items.length ? `<div class="table-scroll" tabindex="0" role="region" aria-label="Intentos registrados"><table><thead><tr>${admin ? '<th>Estudiante</th>' : ''}<th>Tema y materia</th><th>Fecha de inicio</th><th>Estado</th><th>Aciertos</th><th>Calificación</th><th>Detalle</th></tr></thead><tbody>${items.map(a => `<tr>${admin ? `<td><strong>${esc(a.nombres)} ${esc(a.apellidos)}</strong><small class="block muted">${esc(a.usuario)}</small></td>` : ''}<td><strong>${esc(a.tema)}</strong><small class="block muted">${esc(a.materia)}</small></td><td>${esc(date(a.iniciado_en))}</td><td><span class="status ${a.estado === 'finalizado' ? 'active' : 'inactive'}">${a.estado === 'finalizado' ? 'Finalizado' : 'En progreso'}</span></td><td>${a.estado === 'finalizado' ? `${Number(a.aciertos)}/10` : '—'}</td><td>${a.estado === 'finalizado' ? `<strong>${Number(a.calificacion)}/100</strong>` : '—'}</td><td>${!admin || a.estado === 'finalizado' ? `<a class="small-button" href="${site(`estudiante/evaluacion.html?id=${a.id}`)}">${a.estado === 'finalizado' ? 'Ver revisión' : 'Retomar →'}</a>` : 'Pendiente'}</td></tr>`).join('')}</tbody></table></div>` : empty('No hay intentos que mostrar.');
}
async function start() {
  const user = await guard(admin ? 'administrador' : 'estudiante'); if (!user) return;
  attempts = (await request(admin ? 'admin/resultados' : 'historial')).intentos;
  shell(user, admin ? 'resultados' : 'historial', `${heading(admin ? 'Resultados de aprendizaje' : 'Tu camino de aprendizaje', admin ? 'Consulta los intentos, las respuestas y las calificaciones de tus estudiantes.' : 'Retoma una evaluación pendiente o revisa lo que aprendiste.')}<section class="panel students-panel"><div class="list-toolbar"><h2>${admin ? 'Todos los intentos' : 'Mis evaluaciones'}</h2><div class="filters"><label class="search-label"><span class="sr-only">Buscar intentos</span><input id="history-search" type="search" placeholder="Buscar estudiante, tema o materia…"></label><label><span class="sr-only">Estado</span><select id="history-state" aria-label="Estado"><option value="">Todos los estados</option><option value="en_progreso">En progreso</option><option value="finalizado">Finalizados</option></select></label></div></div><div id="history-body"></div></section>`);
  draw(); document.querySelector('#history-search').oninput = draw; document.querySelector('#history-state').onchange = draw;
}
start().catch(pageError);
