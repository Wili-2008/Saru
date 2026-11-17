import { guard, request, site } from './api.js';
import { shell, esc, pageError } from './ui.js';
import { heading, empty, field, textArea, activeField, selectField, modal, formData } from './learning-ui.js';

let user; let quizzes = []; let topics = []; let quiz; let questions = [];
const quizId = Number(new URLSearchParams(location.search).get('id'));
async function renderQuizzes() {
  quizzes = (await request('admin/cuestionarios')).cuestionarios;
  shell(user, 'cuestionarios', `${heading('Cuestionarios', 'Un cuestionario por tema. Actívalo cuando tenga al menos 10 preguntas válidas.', '<button id="new-quiz" class="button primary">+ Nuevo cuestionario</button>')}<div class="cards-grid">${quizzes.length ? quizzes.map(q => `<article class="panel topic-card"><span class="section-tag">${esc(q.materia)}</span><h2>${esc(q.titulo)}</h2><p class="muted">${esc(q.tema)}</p><p><span class="status ${Number(q.activo) ? 'active' : 'inactive'}">${Number(q.activo) ? 'Activo' : 'Inactivo'}</span> <span class="muted">${Number(q.preguntas_validas)} preguntas válidas</span></p><div class="card-actions"><a class="button primary" href="${site(`administrador/preguntas.html?id=${q.id}`)}">Banco de preguntas</a><button class="button secondary" data-edit="${Number(q.id)}">Editar</button></div></article>`).join('') : empty('Crea tu primer cuestionario.')}</div>`);
  document.querySelector('#new-quiz').onclick = () => editQuiz();
  document.querySelectorAll('[data-edit]').forEach(button => button.onclick = () => editQuiz(quizzes.find(q => Number(q.id) === Number(button.dataset.edit))));
}
function editQuiz(item) {
  modal(item ? 'Editar cuestionario' : 'Nuevo cuestionario', `${field('Título', 'titulo', item?.titulo, 'required maxlength="200"')}${item ? `<p class="muted">${esc(item.tema)} · ${Number(item.preguntas_validas)} preguntas válidas</p>` : selectField('Tema', 'tema_id', topics, null, 'titulo')}${activeField(item ? Number(item.activo) === 1 : false)}`, async form => {
    const data = formData(form); data.activo = form.elements.activo.checked;
    if (!item) data.tema_id = Number(data.tema_id);
    await request(`admin/cuestionarios${item ? `/${item.id}` : ''}`, data);
    return renderQuizzes;
  }, 'Cuestionario guardado.', 'Para activar se requieren al menos 10 preguntas activas con cuatro opciones y una correcta.');
}
async function renderQuestions() {
  const data = await request(`admin/cuestionarios/${quizId}/preguntas`); quiz = data.cuestionario; questions = data.preguntas;
  shell(user, 'cuestionarios', `${heading(quiz.titulo, 'Cada pregunta tiene cuatro alternativas y una única respuesta correcta.', '<button id="new-question" class="button primary">+ Nueva pregunta</button>')}<a class="back-link top-back" href="${site('administrador/cuestionarios.html')}">← Todos los cuestionarios</a><div class="notice info">${questions.filter(q => Number(q.activo)).length} preguntas activas. Después de cargar al menos 10 válidas, activa el cuestionario en su ficha. Los cambios no alteran los intentos previos.</div><section class="panel"><div class="record-list">${questions.length ? questions.map((q, index) => `<article class="record-row"><div class="record-copy"><span class="section-tag">PREGUNTA ${index + 1}</span><h3>${esc(q.enunciado)}</h3><p>${esc(q.explicacion || 'Sin explicación adicional.')}</p></div><div class="record-controls"><span class="status ${Number(q.activo) ? 'active' : 'inactive'}">${Number(q.activo) ? 'Activa' : 'Inactiva'}</span><button class="small-button" data-question="${Number(q.id)}">Editar</button></div></article>`).join('') : empty('Agrega la primera pregunta.')}</div></section>`);
  document.querySelector('#new-question').onclick = () => editQuestion();
  document.querySelectorAll('[data-question]').forEach(button => button.onclick = () => editQuestion(questions.find(q => Number(q.id) === Number(button.dataset.question))));
}
function editQuestion(item) {
  const options = item?.opciones || [{}, {}, {}, {}];
  modal(item ? 'Editar pregunta' : 'Nueva pregunta', `${textArea('Enunciado', 'enunciado', item?.enunciado, true)}<fieldset class="option-fields"><legend>Alternativas · marca una correcta</legend>${options.map((o, index) => `<div class="option-editor"><input type="radio" name="correcta" value="${index}" required ${Number(o.es_correcta) ? 'checked' : ''} aria-label="Opción ${index + 1} correcta"><label>Opción ${index + 1}<input name="opcion_${index}" value="${esc(o.texto)}" required maxlength="4000"></label></div>`).join('')}</fieldset>${textArea('Explicación (se muestra después de finalizar)', 'explicacion', item?.explicacion)}${activeField(item ? Number(item.activo) === 1 : true)}`, async form => {
    const data = formData(form);
    const payload = { enunciado: data.enunciado, explicacion: data.explicacion, activo: form.elements.activo.checked,
      opciones: [0, 1, 2, 3].map(index => ({ texto: data[`opcion_${index}`], es_correcta: Number(data.correcta) === index })) };
    await request(item ? `admin/preguntas/${item.id}` : `admin/cuestionarios/${quizId}/preguntas`, payload);
    return renderQuestions;
  }, 'Pregunta guardada. Si quedan menos de 10 válidas, el cuestionario se desactiva automáticamente.');
}
async function start() {
  user = await guard('administrador'); if (!user) return;
  if (document.body.dataset.page === 'preguntas') {
    if (!Number.isSafeInteger(quizId) || quizId < 1) throw new Error('Selecciona un cuestionario válido.');
    await renderQuestions();
  } else { topics = (await request('admin/temas')).temas; await renderQuizzes(); }
}
start().catch(pageError);
