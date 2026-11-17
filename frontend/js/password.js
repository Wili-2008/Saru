import { guard, request, home } from './api.js';
import { shell, notice, pageError, verifyPasswords, passwordToggles } from './ui.js';

async function start() {
  const user = await guard(null, true);
  if (!user) return;
  shell(user, 'password', `<div class="page-heading"><div><p class="eyebrow">CUIDA TU ACCESO</p><h1>Tu contraseña, tu espacio.</h1><p class="muted">${user.cambiar_password ? 'Antes de continuar, reemplaza la contraseña temporal por una que solo tú conozcas.' : 'Actualiza tu contraseña cuando lo necesites.'}</p></div></div><section class="panel password-panel">${user.cambiar_password ? '<div class="notice info">Debes completar este cambio para acceder a tu panel.</div>' : ''}<h2>Cambiar contraseña</h2><form id="password-form" class="form-stack"><label>Contraseña actual<span class="password-field"><input name="actual" type="password" required maxlength="72" autocomplete="current-password"><button class="show-password" type="button" aria-label="Mostrar contraseña" aria-pressed="false">Ver</button></span></label><label>Nueva contraseña<input name="nueva" type="password" required minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="password-help"></label><small id="password-help" class="muted">Elige una frase de al menos 12 caracteres (máximo 72 bytes), diferente a la actual.</small><label>Confirmar nueva contraseña<input name="confirmar" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><button class="button primary" type="submit">Guardar contraseña <span>↗</span></button><p id="password-message" role="status" aria-live="polite"></p></form></section>`);
  // El botón Ver no forma parte del nombre accesible del campo.
  const currentInput = document.querySelector('[name=actual]');
  currentInput.setAttribute('aria-label', 'Contraseña actual');
  passwordToggles();
  const form = document.querySelector('#password-form');
  form.addEventListener('submit', async event => {
    event.preventDefault(); const button = form.querySelector('[type=submit]'); button.disabled = true;
    const target = document.querySelector('#password-message'); notice('', 'error', target);
    try {
      verifyPasswords(form, 'nueva');
      const { usuario } = await request('auth/password', Object.fromEntries(new FormData(form)));
      form.reset(); location.replace(home(usuario));
    } catch (error) {
      if (error.status === 401) { pageError(error); return; }
      notice(error.message, 'error', target); button.disabled = false;
    }
  });
}
start().catch(pageError);
