const base = new URL('../', import.meta.url);
// Único punto de configuración. El VirtualHost publica backend/api/index.php como /api/.
const endpoint = new URL('api/', base);
let csrf = '';

export const site = (path = '') => new URL(path, base).href;
export function apiUrl(route, params = {}) {
  const url = new URL(endpoint); url.searchParams.set('route', route);
  Object.entries(params).forEach(([key, value]) => { if (value !== '' && value !== null) url.searchParams.set(key, value); });
  return url.href;
}
export class ApiError extends Error {
  constructor(message, status) { super(message); this.status = status; }
}

export async function request(route, data, params = {}) {
  const url = apiUrl(route, params);
  const multipart = data instanceof FormData;
  let response;
  try {
    response = await fetch(url, {
      method: data === undefined ? 'GET' : 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: data === undefined ? { Accept: 'application/json' } : multipart ? { Accept: 'application/json', 'X-CSRF-Token': csrf } : {
        Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf,
      },
      ...(data === undefined ? {} : { body: multipart ? data : JSON.stringify(data) }),
    });
  } catch { throw new ApiError('No se pudo conectar con SARU. Comprueba WampServer y tu conexión.', 0); }
  let result;
  try { result = await response.json(); }
  catch { throw new ApiError('La API no devolvió JSON. Revisa la configuración /api/ de Apache.', response.status); }
  if (!response.ok || !result.estado) { throw new ApiError(result.message || 'No se pudo completar la operación.', response.status); }
  if (result.data?.csrf) csrf = result.data.csrf;
  return result.data;
}

export const session = () => request('sesion');
export function home(user) {
  return site(user.cambiar_password ? 'cuenta/password.html' : user.rol === 'administrador' ? 'administrador/' : 'estudiante/');
}

export async function guard(role, allowPasswordChange = false) {
  const { usuario } = await session();
  if (!usuario) { location.replace(site()); return null; }
  if ((usuario.cambiar_password && !allowPasswordChange) || (role && usuario.rol !== role)) {
    location.replace(home(usuario)); return null;
  }
  return usuario;
}
