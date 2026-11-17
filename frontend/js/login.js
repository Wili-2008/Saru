import { session, request, home } from './api.js';
import { notice, passwordToggles } from './ui.js';

const form = document.querySelector('#login-form');
const button = form.querySelector('[type=submit]');
passwordToggles();
async function start() {
  try {
    const { usuario } = await session();
    if (usuario) { location.replace(home(usuario)); return; }
    button.disabled = false;
  } catch (error) { notice(error.message + ' Recarga la página para reintentar.'); }
}
form.addEventListener('submit', async event => {
  event.preventDefault(); button.disabled = true; notice();
  try {
    const { usuario } = await request('auth/login', Object.fromEntries(new FormData(form)));
    form.reset(); location.replace(home(usuario));
  } catch (error) { notice(error.message); form.elements.password.value = ''; button.disabled = false; }
});
start();
