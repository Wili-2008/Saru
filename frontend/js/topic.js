import { guard, request, site } from './api.js';
import { shell, esc, pageError } from './ui.js';
import { heading, empty, action } from './learning-ui.js';

async function start() {
  const user = await guard('estudiante'); if (!user) return;
  const id = Number(new URLSearchParams(location.search).get('id'));
  if (!Number.isSafeInteger(id) || id < 1) throw new Error('Selecciona un tema válido.');
  const data = await request(`catalogo/temas/${id}`); const t = data.tema;
  shell(user, 'inicio', `<a class="back-link top-back" href="${site('estudiante/')}">← Volver a mis materias</a>${heading(t.titulo, t.materia)}<p class="topic-description">${esc(t.descripcion || '')}</p><div class="two-column"><section class="panel"><span class="section-tag">PARA LEER Y EXPLORAR</span><h2>Materiales de aprendizaje</h2>${data.materiales.length ? `<div class="record-list">${data.materiales.map(m => `<article class="record-row"><div class="record-copy"><span class="section-tag">${esc(m.tipo)} · ${(Number(m.tamano_bytes) / 1048576).toFixed(1)} MB</span><h3>${esc(m.titulo)}</h3><p>${esc(m.descripcion)}</p></div><a class="button secondary" href="${site(`estudiante/visor.html?id=${m.id}&tema=${id}`)}">Leer PDF ↗</a></article>`).join('')}</div>` : empty('Aún no hay materiales para este tema.')}</section><section class="panel"><span class="section-tag">AMPLÍA TU APRENDIZAJE</span><h2>Recursos complementarios</h2>${data.recursos.length ? `<div class="resource-list">${data.recursos.map(r => `<a href="${esc(r.url)}" target="_blank" rel="noopener noreferrer" class="resource-card"><span class="section-tag">${esc(r.tipo)}</span><h3>${esc(r.titulo)} ↗</h3><p class="muted">${esc(r.descripcion)}</p></a>`).join('')}</div>` : empty('Aún no hay enlaces complementarios.')}</section></div><section class="panel assessment-banner"><div><span class="section-tag">PON EN PRÁCTICA LO APRENDIDO</span><h2>${esc(data.cuestionario?.titulo || 'Tu próxima evaluación')}</h2><p class="muted">${data.cuestionario ? 'Diez preguntas, a tu ritmo. Puedes revisar tus respuestas antes de finalizar y repetir cuando quieras.' : 'El cuestionario estará disponible cuando tu administrador lo active.'}</p></div>${data.cuestionario ? '<button class="button primary" id="start-attempt">Comenzar o retomar →</button>' : '<span class="pill">Aún no disponible</span>'}</section>`);
  document.querySelector('#start-attempt')?.addEventListener('click', event => action(event.currentTarget, 'intentos', { cuestionario_id: Number(data.cuestionario.id) }, result => {
    location.href = site(`estudiante/evaluacion.html?id=${result.id}`);
  }));
}
start().catch(pageError);
