const BASE = import.meta.env.VITE_API_BASE || '/api';
let csrfToken = null;

export class ApiError extends Error {
  constructor(status, message, fields = {}, code = null) {
    super(message);
    this.status = status;
    this.fields = fields || {};
    this.code = code;
  }
}

export const setCsrf = (t) => { csrfToken = t; };

async function raw(method, path, { body, form, query, signal, timeout = 30000 } = {}) {
  const url = new URL(BASE + path, window.location.origin);
  if (query) Object.entries(query).forEach(([k, v]) => v !== '' && v != null && url.searchParams.set(k, v));
  const headers = { Accept: 'application/json' };
  let payload;
  if (form) payload = form;
  else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
  if (method !== 'GET' && csrfToken) headers['X-CSRF-Token'] = csrfToken;

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeout);
  signal?.addEventListener('abort', () => ctrl.abort());
  try {
    const res = await fetch(url, { method, headers, body: payload, credentials: 'same-origin', cache: 'no-store', signal: ctrl.signal });
    let data = null;
    try { data = await res.json(); } catch { /* non-JSON */ }
    if (!res.ok) {
      const e = data?.error || {};
      throw new ApiError(res.status, e.message || `Request failed (${res.status})`, e.fields, e.code);
    }
    return data;
  } finally {
    clearTimeout(timer);
  }
}

async function request(method, path, opts) {
  try {
    return await raw(method, path, opts);
  } catch (e) {
    if (e instanceof ApiError && e.code === 'csrf_invalid') {
      const me = await raw('GET', '/admin/me');
      csrfToken = me.data.csrf_token;
      return raw(method, path, opts);
    }
    if (e instanceof ApiError && e.status === 401 && path !== '/admin/login' && path !== '/admin/me') {
      window.dispatchEvent(new Event('auth:expired'));
    }
    throw e;
  }
}

export const api = {
  get: (p, o) => request('GET', p, o),
  post: (p, body, o) => request('POST', p, { ...o, body }),
  patch: (p, body, o) => request('PATCH', p, { ...o, body }),
  del: (p, o) => request('DELETE', p, o),
  upload: (p, form, o) => request('POST', p, { ...o, form, timeout: 120000 }),
};
