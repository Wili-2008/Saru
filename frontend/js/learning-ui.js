import { request } from './api.js';
import { esc, notice, pageError } from './ui.js';

export const heading = (title, description, action = '') => `<div class="page-heading"><div><p class="eyebrow">SARU · APRENDER PARA AVANZAR</p><h1>${esc(title)}</h1><p class="muted">${esc(description)}</p></div>${action}</div>`;
export const empty = (text = 'Todavía no hay registros disponibles.') => `<div class="empty-state"><span aria-hidden="true">✦</span><h3>${esc(text)}</h3><p>Cuando haya contenido disponible aparecerá aquí.</p></div>`;
export const options = (items, selected, titleKey = 'nombre') => `<option value="">Selecciona una opción</option>${items.map(i => `<option value="${Number(i.id)}" ${Number(i.id) === Number(selected) ? 'selected' : ''}>${esc(i[titleKey])}</option>`).join('')}`;
export const field = (label, name, value = '', config = '') => `<label>${esc(label)}<input name="${name}" value="${esc(value)}" ${config}></label>`;
export const textArea = (label, name, value = '', required = false, max = 10000) => `<label>${esc(label)}<textarea name="${name}" rows="4" maxlength="${max}" ${required ? 'required' : ''}>${esc(value)}</textarea></label>`;
export const activeField = active => `<label class="check-label"><input name="activo" type="checkbox" ${active ? 'checked' : ''}> Cuenta o contenido activo</label>`;
export const selectField = (label, name, entries, selected, titleKey = 'nombre') => `<label>${esc(label)}<select aria-label="${esc(label)}" name="${name}" required>${options(entries, selected, titleKey)}</select></label>`;
export const formData = form => Object.fromEntries(new FormData(form));
export const date = raw => {
  if (!raw) return '—';
  const value = new Date(String(raw).replace(' ', 'T'));
  return Number.isNaN(value.getTime()) ? String(raw) : value.toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' });
};

export function modal(title, fields, onSave, success = 'Cambios guardados.', description = '') {
  let dialog = document.querySelector('#edit-dialog');
  if (!dialog) { dialog = document.createElement('dialog'); dialog.id = 'edit-dialog'; document.body.append(dialog); }
  dialog.setAttribute('aria-label', title);
  dialog.innerHTML = `<div class="dialog-heading"><div><span class="section-tag">ADMINISTRACIÓN</span><h2>${esc(title)}</h2></div><button type="button" class="dialog-close" aria-label="Cerrar">×</button></div>${description ? `<p class="muted">${esc(description)}</p>` : ''}<div id="edit-notice" role="status" aria-live="polite"></div><form class="form-stack">${fields}<div class="dialog-actions"><button type="button" class="button secondary cancel">Cancelar</button><button class="button primary" type="submit">Guardar cambios</button></div></form>`;
  const form = dialog.querySelector('form');
  const close = () => { dialog.close(); form.reset(); };
  dialog.querySelector('.dialog-close').onclick = close;
  dialog.querySelector('.cancel').onclick = close;
  form.onsubmit = async event => {
    event.preventDefault();
    const button = form.querySelector('[type=submit]'); button.disabled = true;
    try {
      const refresh = await onSave(form); close();
      try { if (refresh) await refresh(); notice(success, 'success'); }
      catch (error) { notice(`La operación se guardó. No se pudo recargar la lista: ${error.message}`); }
    } catch (error) {
      if (error.status === 401) { pageError(error); return; }
      notice(error.message, 'error', dialog.querySelector('#edit-notice')); button.disabled = false;
    }
  };
  dialog.showModal();
}

export async function action(button, route, body, onSuccess) {
  button.disabled = true;
  try { const result = await request(route, body); await onSuccess(result); }
  catch (error) { if (error.status === 401) pageError(error); else notice(error.message); }
  finally { button.disabled = false; }
}
