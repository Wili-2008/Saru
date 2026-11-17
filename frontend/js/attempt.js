import { guard, request, site } from './api.js';
import { shell, esc, notice, pageError } from './ui.js';
import { heading } from './learning-ui.js';

const id = Number(new URLSearchParams(location.search).get('id'));
let user; let attempt; let questions = []; let index = 0; let review = false; let busy = false;
const answered = () => questions.filter(q => q.seleccionada !== null).length;
const done = () => attempt.estado === 'finalizado';
const selectedText = q => q.opciones.find(o => Number(o.id) === Number(q.seleccionada))?.texto || 'Sin responder';
function draw() {
  const target = document.querySelector('#attempt-content');
  if (done()) {
    target.innerHTML = `<section class="score-card"><span class="section-tag">EVALUACIÓN FINALIZADA</span><h2>Un paso más en tu aprendizaje.</h2><div class="score-value">${Number(attempt.calificacion)}<small>/100</small></div><p>${Number(attempt.aciertos)} respuestas correctas de 10.</p><a class="button primary" href="${site(user.rol === 'administrador' ? 'administrador/resultados.html' : 'estudiante/historial.html')}">Volver al historial →</a></section><div class="review-results">${questions.map((q, i) => {
      const chosen = q.opciones.find(o => Number(o.id) === Number(q.seleccionada)); const correct = Number(chosen?.es_correcta) === 1;
      return `<article class="panel result-question"><div class="panel-title"><span class="section-tag">PREGUNTA ${i + 1}</span><span class="status ${correct ? 'active' : 'inactive'}">${correct ? 'Correcta' : 'Por repasar'}</span></div><h2>${esc(q.enunciado)}</h2><div class="result-options">${q.opciones.map(o => `<div class="result-option ${Number(o.es_correcta) ? 'correct-option' : Number(o.id) === Number(q.seleccionada) ? 'incorrect-option' : ''}"><span>${Number(o.es_correcta) ? '✓' : Number(o.id) === Number(q.seleccionada) ? '×' : '○'}</span><p>${esc(o.texto)}</p><small>${Number(o.id) === Number(q.seleccionada) ? 'Tu respuesta' : Number(o.es_correcta) ? 'Respuesta correcta' : ''}</small></div>`).join('')}</div>${q.explicacion ? `<div class="explanation"><strong>Para seguir aprendiendo</strong><p>${esc(q.explicacion)}</p></div>` : ''}</article>`;
    }).join('')}</div>`;
    return;
  }
  if (user.rol !== 'estudiante') {
    target.innerHTML = '<div class="notice info">El estudiante aún no finalizó esta evaluación. Las respuestas correctas se mostrarán después de finalizar.</div>'; return;
  }
  if (review) {
    target.innerHTML = `<section class="panel"><span class="section-tag">ANTES DE FINALIZAR</span><h2>Revisa tus respuestas</h2><p class="muted">${answered()} de 10 respondidas. Puedes volver a cualquier pregunta y cambiar tu elección.</p><div class="review-list">${questions.map((q, i) => `<button type="button" class="review-item" data-jump="${i}"><span class="question-number">${i + 1}</span><span><strong>${esc(q.enunciado)}</strong><small>${esc(selectedText(q))}</small></span><span>${q.seleccionada !== null ? '✓' : '○'}</span></button>`).join('')}</div><div class="attempt-actions"><button class="button secondary" data-back>← Volver a las preguntas</button><button class="button primary" id="finish-attempt" ${answered() !== 10 || busy ? 'disabled' : ''}>Finalizar evaluación →</button></div><p class="table-footnote">Después de finalizar, las respuestas quedarán guardadas y ya no podrán modificarse.</p></section>`;
    target.querySelectorAll('[data-jump]').forEach(button => button.onclick = () => { index = Number(button.dataset.jump); review = false; draw(); });
    target.querySelector('[data-back]').onclick = () => { review = false; draw(); };
    target.querySelector('#finish-attempt').onclick = finish;
    return;
  }
  const q = questions[index];
  target.innerHTML = `<div class="attempt-progress"><p>Pregunta <strong>${index + 1} de 10</strong></p><p>${answered()} respuestas guardadas</p></div><div class="question-navigation" aria-label="Preguntas de la evaluación">${questions.map((question, i) => `<button type="button" data-jump="${i}" class="${i === index ? 'current' : question.seleccionada !== null ? 'answered' : ''}" aria-label="Ir a la pregunta ${i + 1}${question.seleccionada !== null ? ', respondida' : ''}" ${i === index ? 'aria-current="step"' : ''}>${i + 1}</button>`).join('')}</div><section class="panel question-panel"><span class="section-tag">ELIGE UNA RESPUESTA</span><h2 id="question-heading">${esc(q.enunciado)}</h2><fieldset class="answer-options" aria-labelledby="question-heading"><legend class="sr-only">Opciones de respuesta</legend>${q.opciones.map((o, i) => `<label class="answer-option ${Number(o.id) === Number(q.seleccionada) ? 'chosen' : ''}"><input type="radio" name="answer" value="${Number(o.id)}" ${Number(o.id) === Number(q.seleccionada) ? 'checked' : ''}><span class="option-letter">${String.fromCharCode(65 + i)}</span><span>${esc(o.texto)}</span></label>`).join('')}</fieldset><p class="save-hint" role="status">${q.seleccionada !== null ? '✓ Respuesta guardada. Puedes cambiarla.' : 'Selecciona una alternativa. Tu respuesta se guardará automáticamente.'}</p><div class="attempt-actions"><button class="button secondary" id="previous" ${index === 0 ? 'disabled' : ''}>← Anterior</button><button class="button primary" id="next">${index === 9 ? 'Revisar respuestas →' : 'Siguiente →'}</button></div></section><p class="table-footnote">Sin límite de tiempo. Puedes salir y retomar esta evaluación desde tu historial.</p>`;
  target.querySelector('#previous').onclick = () => { if (!busy && index > 0) { index--; draw(); } };
  target.querySelector('#next').onclick = () => { if (!busy) { if (index < 9) index++; else review = true; draw(); } };
  target.querySelectorAll('[data-jump]').forEach(button => button.onclick = () => { if (!busy) { index = Number(button.dataset.jump); draw(); } });
  target.querySelectorAll('[name=answer]').forEach(input => input.onchange = async () => {
    if (busy) return;
    busy = true; notice();
    target.querySelectorAll('button,input').forEach(element => element.disabled = true);
    target.querySelector('.save-hint').textContent = 'Guardando respuesta…';
    try {
      await request(`intentos/${id}/respuesta`, { pregunta_id: Number(q.id), opcion_id: Number(input.value) });
      q.seleccionada = Number(input.value);
    } catch (error) {
      if (error.status === 401) { pageError(error); return; }
      notice('No se pudo guardar esta elección: ' + error.message);
    } finally { busy = false; if (document.querySelector('#attempt-content')) draw(); }
  });
}
async function finish() {
  if (busy) return; busy = true;
  document.querySelector('#finish-attempt').disabled = true; notice();
  try {
    await request(`intentos/${id}/finalizar`, {});
    const data = await request(`intentos/${id}`); attempt = data.intento; questions = data.preguntas; review = false;
    draw(); window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (error) {
    if (error.status === 401) { pageError(error); return; }
    notice(error.message);
  } finally { busy = false; const button = document.querySelector('#finish-attempt'); if (button) button.disabled = false; }
}
async function start() {
  user = await guard(); if (!user) return;
  if (!Number.isSafeInteger(id) || id < 1) throw new Error('Selecciona una evaluación desde el tema o el historial.');
  const data = await request(`intentos/${id}`); attempt = data.intento; questions = data.preguntas;
  if (questions.length !== 10) throw new Error('Esta evaluación no contiene las 10 preguntas esperadas. Contacta al administrador.');
  shell(user, user.rol === 'administrador' ? 'resultados' : 'historial', `${heading(attempt.titulo, `${attempt.materia} · ${attempt.tema}`)}<div id="attempt-content"></div>`);
  draw();
}
start().catch(pageError);
