import { guard, apiUrl, site } from './api.js';
import { shell, esc, pageError } from './ui.js';
import { heading } from './learning-ui.js';

async function start() {
  const user = await guard(); if (!user) return;
  const params = new URLSearchParams(location.search); const id = Number(params.get('id')); const topic = Number(params.get('tema'));
  if (!Number.isSafeInteger(id) || id < 1) throw new Error('Selecciona un material válido.');
  const url = apiUrl(`materiales/${id}/archivo`);
  const response = await fetch(url, { credentials: 'same-origin', method: 'GET' });
  if (!response.ok || !response.headers.get('content-type')?.includes('application/pdf')) {
    let message = 'El material no está disponible.';
    try { message = (await response.json()).message || message; } catch { /* Mensaje general */ }
    throw new Error(message);
  }
  if (response.body) await response.body.cancel();
  shell(user, 'inicio', `${heading('Un nuevo descubrimiento', 'Lee el material a tu ritmo.', `<a class="button primary" target="_blank" rel="noopener" href="${esc(url)}">Abrir PDF en otra pestaña ↗</a>`)}<a class="back-link top-back" href="${site(Number.isSafeInteger(topic) && topic > 0 ? `estudiante/tema.html?id=${topic}` : 'estudiante/')}">← Volver al tema</a><p class="muted">Si tu navegador no muestra el documento aquí, utiliza el botón para abrirlo en otra pestaña.</p><iframe class="pdf-viewer" title="Visor del material PDF" src="${esc(url)}"></iframe>`);
}
start().catch(pageError);
