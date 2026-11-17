import { request, site, home } from './api.js';

// Revalidar la sesión al volver desde la caché de navegación del navegador.
window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });

export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
export function notice(message = '', kind = 'error', target = document.querySelector('#notice')) {
  if (!target) return;
  target.className = message ? `notice ${kind}` : '';
  target.textContent = message;
}

export function passwordToggles(root = document) {
  root.querySelectorAll('.show-password').forEach(button => button.addEventListener('click', () => {
    const input = button.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Ocultar' : 'Ver';
    button.setAttribute('aria-pressed', String(show));
    button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
  }));
}

export function shell(user, active, content) {
  const admin = user.rol === 'administrador';
  document.querySelector('#app').innerHTML = `<div class="workspace"><aside class="sidebar"><a class="brand" href="${home(user)}"><span class="brand-mark">S<span>↗</span></span><span>SARU<small>Aprender para avanzar</small></span></a><p class="nav-label">MI ESPACIO</p><nav aria-label="Navegación principal"><a class="nav-link ${active === 'inicio' ? 'active' : ''}" href="${site(admin ? 'administrador/' : 'estudiante/')}"><span aria-hidden="true">▦</span> Inicio</a>${admin ? `<a class="nav-link ${active === 'estudiantes' ? 'active' : ''}" href="${site('administrador/estudiantes.html')}"><span aria-hidden="true">♧</span> Estudiantes</a>` : ''}<a class="nav-link ${active === 'password' ? 'active' : ''}" href="${site('cuenta/password.html')}"><span aria-hidden="true">⚿</span> Mi contraseña</a></nav><div class="sidebar-bottom"><div class="school-label"><span>✦</span><p>Un paso a la vez.<small>El aprendizaje continúa.</small></p></div><button class="logout" type="button">↗ <span>Cerrar sesión</span></button></div></aside><div class="main-wrap"><header class="topbar"><span class="breadcrumb">Mi espacio <span>/</span> ${active === 'estudiantes' ? 'Estudiantes' : active === 'password' ? 'Mi contraseña' : 'Inicio'}</span><div class="profile"><span class="avatar">${esc(user.nombres.slice(0, 1).toUpperCase())}</span><span><strong>${esc(user.nombres)}</strong><small>${admin ? 'Administrador' : 'Estudiante'}</small></span></div></header><main class="content"><div id="notice" role="status" aria-live="polite"></div>${content}</main><footer class="workspace-footer"><span>SARU · Sistema de Aprendizaje Rápido</span><span>Sexto de secundaria</span></footer></div></div>`;
  const links = admin ? [['inicio', 'Inicio', 'administrador/'], ['estudiantes', 'Estudiantes', 'administrador/estudiantes.html'], ['contenidos', 'Contenidos', 'administrador/contenidos.html'], ['cuestionarios', 'Cuestionarios', 'administrador/cuestionarios.html'], ['resultados', 'Resultados', 'administrador/resultados.html'], ['password', 'Mi contraseña', 'cuenta/password.html']] : [['inicio', 'Mis materias', 'estudiante/'], ['historial', 'Mi historial', 'estudiante/historial.html'], ['password', 'Mi contraseña', 'cuenta/password.html']];
  document.querySelector('.sidebar nav').innerHTML = links.map(([key, label, path]) => `<a class="nav-link ${key === active ? 'active' : ''}" href="${site(path)}">${label}</a>`).join('');
  document.querySelector('.breadcrumb').textContent = 'Mi espacio / ' + (links.find(([key]) => key === active)?.[1] || 'Evaluación');
  document.querySelector('.logout').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    try { await request('auth/logout', {}); location.replace(site()); }
    catch (error) { notice(error.message); button.disabled = false; }
  });
}

export function pageError(error) {
  if (error.status === 401) { location.replace(site()); return; }
  const root = document.querySelector('#app');
  root.innerHTML = `<main class="setup-card"><span class="section-tag">SARU</span><h1>No pudimos cargar esta página.</h1><p class="muted">${esc(error.message)}</p><a class="button primary" href="${esc(location.href)}">Intentar nuevamente</a><a class="back-link" href="${site()}">Volver al inicio</a></main>`;
}

export function verifyPasswords(form, first = 'password', second = 'confirmar') {
  const a = form.elements.namedItem(first).value;
  const b = form.elements.namedItem(second).value;
  if (a !== b) throw new Error('Las contraseñas no coinciden.');
  if (new TextEncoder().encode(a).length > 72) throw new Error('La contraseña supera los 72 bytes. Usa una frase más corta.');
}
