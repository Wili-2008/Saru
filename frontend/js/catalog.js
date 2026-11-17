import { guard, request, site } from './api.js';
import { shell, esc, pageError, notice } from './ui.js';
import { heading, empty } from './learning-ui.js';

let user; let subjects = []; let topics = []; let generation = 0;
function draw() {
  document.querySelector('#topic-grid').innerHTML = topics.length ? topics.map(t => `<a class="panel topic-card" href="${site(`estudiante/tema.html?id=${t.id}`)}"><span class="section-tag">${esc(t.materia)}</span><h2>${esc(t.titulo)}</h2><p class="muted">${esc(t.descripcion || 'Explora los materiales y recursos de este tema.')}</p><span class="text-link">Explorar tema →</span></a>`).join('') : empty('Todavía no hay temas publicados que coincidan.');
  document.querySelector('#topics-count').textContent = `${topics.length} temas disponibles`;
}
async function search() {
  const current = ++generation;
  const form = document.querySelector('#catalog-search');
  try {
    const data = await request('catalogo', undefined, { q: form.elements.q.value, materia_id: form.elements.materia_id.value });
    if (current !== generation) return;
    topics = data.temas; notice(); draw();
  } catch (error) { if (current === generation) notice(error.message); }
}
async function start() {
  user = await guard('estudiante'); if (!user) return;
  const data = await request('catalogo'); subjects = data.materias; topics = data.temas;
  shell(user, 'inicio', `${heading(`Hola, ${user.nombres}`, 'Tu siguiente descubrimiento empieza aquí.')}<section class="welcome-banner"><div><span class="section-tag light-tag">TU ESPACIO DE APRENDIZAJE</span><h2>Haz preguntas.<br>Encuentra nuevos caminos.</h2><p>Explora tus materias, consulta los materiales y pon a prueba lo que aprendiste.</p><a class="button lime" href="${site('estudiante/historial.html')}">Ver mi historial <span>↗</span></a></div><div class="banner-art" aria-hidden="true">↗<span>APRENDE<br>A TU RITMO</span></div></section><div class="subject-strip">${subjects.map(m => `<button class="subject-chip" data-subject="${Number(m.id)}">${esc(m.nombre)}</button>`).join('')}</div><section class="panel catalog-toolbar"><div><h2>Explora tus temas</h2><p class="muted" id="topics-count"></p></div><form id="catalog-search" class="catalog-search"><label><span class="sr-only">Buscar temas</span><input type="search" name="q" placeholder="Buscar por título o descripción…" maxlength="200"></label><label><span class="sr-only">Materia</span><select name="materia_id" aria-label="Materia"><option value="">Todas las materias</option>${subjects.map(m => `<option value="${Number(m.id)}">${esc(m.nombre)}</option>`).join('')}</select></label><button class="button primary" type="submit">Buscar</button></form></section><div id="topic-grid" class="cards-grid"></div>`);
  draw();
  document.querySelector('#catalog-search').onsubmit = event => { event.preventDefault(); search(); };
  document.querySelector('[name=materia_id]').onchange = search;
  document.querySelectorAll('[data-subject]').forEach(button => button.onclick = () => {
    document.querySelector('[name=materia_id]').value = button.dataset.subject; search();
  });
}
start().catch(pageError);
