import { session, request, site } from './api.js';
import { notice, verifyPasswords } from './ui.js';

const form = document.querySelector('#install-form');
async function start() {
  await session();
  const { disponible } = await request('instalacion');
  if (!disponible) {
    notice('La instalación está deshabilitada. Si aún no existe un administrador, habilítala desde la consola local siguiendo README.md.', 'info');
    return;
  }
  form.hidden = false;
}
form.addEventListener('submit', async event => {
  event.preventDefault(); const button = form.querySelector('[type=submit]'); button.disabled = true; notice();
  try {
    verifyPasswords(form);
    await request('instalacion', Object.fromEntries(new FormData(form)));
    form.reset(); form.hidden = true;
    notice('Administrador creado. La instalación quedó bloqueada. Ya puedes iniciar sesión.', 'success');
    const link = document.querySelector('.back-link'); link.href = site(); link.textContent = 'Ir al inicio de sesión →';
  } catch (error) { notice(error.message); button.disabled = false; }
});
start().catch(error => notice(error.message));
