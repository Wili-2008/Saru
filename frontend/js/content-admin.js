import { guard, request, apiUrl, site } from './api.js';
import { shell, esc, pageError } from './ui.js';
import { heading, empty, field, textArea, activeField, selectField, modal, formData } from './learning-ui.js';

const types = { materias: 'Materias', temas: 'Temas', materiales: 'Materiales PDF', recursos: 'Enlaces y recursos' };
let type = new URLSearchParams(location.search).get('tipo') || 'materias';
if (!types[type]) type = 'materias';
let user; let items = []; let subjects = []; let topics = []; let courses = [];
async function render() {
  const result = await request(`admin/${type}`); items = result[type];
  shell(user, 'contenidos', `${heading(types[type], 'Prepara contenidos claros y accesibles para tus estudiantes.', `<button id="new-record" class="button primary" type="button">+ Agregar ${type === 'materias' ? 'materia' : type === 'temas' ? 'tema' : type === 'materiales' ? 'PDF' : 'recurso'}</button>`)}<nav class="content-tabs" aria-label="Tipos de contenido">${Object.entries(types).map(([key, label]) => `<a class="${type === key ? 'selected' : ''}" href="${site(`administrador/contenidos.html?tipo=${key}`)}">${label}</a>`).join('')}</nav><section class="panel"><div class="list-toolbar compact-toolbar"><h2>Contenido registrado</h2><label><span class="sr-only">Buscar contenido</span><input id="content-search" type="search" placeholder="Buscar contenido…"></label></div><div id="content-list" class="record-list"></div></section><p class="table-footnote">Para retirar una materia, un material o un recurso, desactívalo. Para retirar un tema, vuelve a borrador. Los intentos previos se conservan.</p>`);
  draw();
  document.querySelector('#content-search').oninput = draw;
  document.querySelector('#new-record').onclick = () => edit();
  document.querySelector('#content-list').onclick = event => {
    const button = event.target.closest('[data-edit]');
    if (button) edit(items.find(item => Number(item.id) === Number(button.dataset.edit)));
  };
}
function draw() {
  const query = document.querySelector('#content-search').value.toLocaleLowerCase('es');
  const shown = items.filter(item => `${item.nombre || item.titulo} ${item.materia || item.tema || item.curso || ''}`.toLocaleLowerCase('es').includes(query));
  document.querySelector('#content-list').innerHTML = shown.length ? shown.map(item => {
    const active = type === 'temas' ? item.estado === 'publicado' : Number(item.activo) === 1;
    return `<article class="record-row"><div class="record-copy"><span class="section-tag">${esc(item.materia || item.tema || item.curso || item.tipo || 'CONTENIDO')}</span><h3>${esc(item.nombre || item.titulo)}</h3><p>${esc(item.descripcion || item.nombre_original || 'Sin descripción adicional.')}</p></div><div class="record-controls"><span class="status ${active ? 'active' : 'inactive'}">${type === 'temas' ? esc(item.estado) : active ? 'Activo' : 'Inactivo'}</span>${type === 'materiales' ? `<a class="small-button" href="${esc(apiUrl(`materiales/${item.id}/archivo`))}" target="_blank" rel="noopener">Ver PDF ↗</a>` : ''}<button class="small-button" type="button" data-edit="${Number(item.id)}">Editar</button></div></article>`;
  }).join('') : empty('No hay contenidos que mostrar.');
}
function edit(item = null) {
  let fields = '';
  if (type === 'materias') fields = `${field('Nombre', 'nombre', item?.nombre, 'required maxlength="120"')}${selectField('Curso', 'curso_id', courses, item?.curso_id)}${textArea('Descripción', 'descripcion', item?.descripcion)}${activeField(item ? Number(item.activo) === 1 : true)}`;
  if (type === 'temas') fields = `${field('Título', 'titulo', item?.titulo, 'required maxlength="200"')}${selectField('Materia', 'materia_id', subjects, item?.materia_id)}${textArea('Descripción', 'descripcion', item?.descripcion)}${field('Orden', 'orden', item?.orden ?? 1, 'type="number" min="1" max="4294967295" required')}<label>Estado<select name="estado" aria-label="Estado"><option value="borrador" ${item?.estado !== 'publicado' ? 'selected' : ''}>Borrador</option><option value="publicado" ${item?.estado === 'publicado' ? 'selected' : ''}>Publicado</option></select></label>`;
  if (type === 'recursos') fields = `${field('Título', 'titulo', item?.titulo, 'required maxlength="200"')}${selectField('Tema', 'tema_id', topics, item?.tema_id, 'titulo')}<label>Tipo<select name="tipo" aria-label="Tipo">${['video', 'libro', 'enlace'].map(v => `<option value="${v}" ${item?.tipo === v ? 'selected' : ''}>${v}</option>`).join('')}</select></label>${field('URL (HTTP o HTTPS)', 'url', item?.url, 'type="url" required maxlength="2048"')}${textArea('Descripción', 'descripcion', item?.descripcion)}${activeField(item ? Number(item.activo) === 1 : true)}`;
  if (type === 'materiales') fields = `${field('Título', 'titulo', item?.titulo, 'required maxlength="200"')}${selectField('Tema', 'tema_id', topics, item?.tema_id, 'titulo')}<label>Tipo<select name="tipo" aria-label="Tipo">${['resumen', 'guia', 'libro', 'otro'].map(v => `<option value="${v}" ${item?.tipo === v ? 'selected' : ''}>${v}</option>`).join('')}</select></label>${textArea('Descripción', 'descripcion', item?.descripcion)}<label>${item ? 'Reemplazar PDF (opcional)' : 'Archivo PDF'}<input name="archivo" type="file" accept="application/pdf,.pdf" ${item ? '' : 'required'}></label><small class="muted">${item ? `Actual: ${esc(item.nombre_original)}. ` : ''}Solo PDF. Límite predeterminado de SARU: 20 MB; también se aplica el límite de PHP.</small>${activeField(item ? Number(item.activo) === 1 : true)}`;
  modal(item ? 'Editar contenido' : 'Agregar contenido', fields, async form => {
    const data = formData(form);
    ['curso_id', 'materia_id', 'tema_id', 'orden'].forEach(key => { if (key in data) data[key] = Number(data[key]); });
    if (type !== 'temas') data.activo = form.elements.activo.checked;
    let payload = data;
    if (type === 'materiales') {
      const file = form.elements.archivo.files[0]; delete data.archivo;
      payload = new FormData(); payload.set('datos', JSON.stringify(data)); if (file) payload.set('archivo', file);
    }
    await request(`admin/${type}${item ? `/${item.id}` : ''}`, payload);
    return render;
  }, 'Contenido guardado.');
}
async function start() {
  user = await guard('administrador'); if (!user) return;
  const data = await Promise.all([request('admin/materias'), request('admin/temas'), request('cursos')]);
  subjects = data[0].materias; topics = data[1].temas; courses = data[2].cursos;
  await render();
}
start().catch(pageError);
