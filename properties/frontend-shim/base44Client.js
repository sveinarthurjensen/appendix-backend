/**
 * Drop-in-erstatning for src/api/base44Client.js.
 *
 * Samme API som @base44/sdk (entities.X.list/filter/get/create/bulkCreate/update/delete,
 * functions.invoke, auth.me/logout), men hver entitet kan pekes mot egen Laravel-backend
 * via VITE_LARAVEL_ENTITIES. Alt annet går fortsatt til Base44 – «strangler»-mønsteret.
 *
 *   VITE_LARAVEL_URL=https://api-staging.appendixholding.no
 *   VITE_LARAVEL_ENTITIES=Property,Booking,Tenant        (eller * for alle)
 *   VITE_LARAVEL_FUNCTIONS=sendSms,brregOppslag          (eller * for alle)
 */
import { createClient } from '@base44/sdk';
import { appParams } from '@/lib/app-params';

const { appId, serverUrl, token, functionsVersion } = appParams;
const legacy = createClient({ appId, serverUrl, token, functionsVersion, requiresAuth: false });

const LARAVEL_URL = import.meta.env.VITE_LARAVEL_URL || '';
const toSet = (s) => new Set((s || '').split(',').map((x) => x.trim()).filter(Boolean));
const LARAVEL_ENTITIES = toSet(import.meta.env.VITE_LARAVEL_ENTITIES);
const LARAVEL_FUNCTIONS = toSet(import.meta.env.VITE_LARAVEL_FUNCTIONS);
const useLaravel = (set, name) => LARAVEL_URL && (set.has('*') || set.has(name));

const TOKEN_KEY = 'appendix_api_token';
export const apiToken = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (t) => localStorage.setItem(TOKEN_KEY, t),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

async function api(method, path, { body, query } = {}) {
  const url = new URL(LARAVEL_URL + '/api' + path);
  if (query) Object.entries(query).forEach(([k, v]) => v !== undefined && url.searchParams.set(k, v));
  const res = await fetch(url, {
    method,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(apiToken.get() ? { Authorization: `Bearer ${apiToken.get()}` } : {}),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (res.status === 401) { apiToken.clear(); }
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw Object.assign(new Error(err.message || `${res.status} ${res.statusText}`), { status: res.status, data: err });
  }
  return res.status === 204 ? null : res.json();
}

function laravelEntity(name) {
  const base = `/entities/${name}`;
  return {
    list: (sort, limit, skip, fields) => api('GET', base, { query: { sort, limit, skip, fields: fields?.join(',') } }),
    filter: (q, sort, limit, skip, fields) => api('GET', base, { query: { q: JSON.stringify(q || {}), sort, limit, skip, fields: fields?.join(',') } }),
    get: (id) => api('GET', `${base}/${id}`),
    create: (data) => api('POST', base, { body: data }),
    bulkCreate: (rows) => api('POST', base, { body: rows }),
    update: (id, data) => api('PATCH', `${base}/${id}`, { body: data }),
    delete: (id) => api('DELETE', `${base}/${id}`),
  };
}

const entities = new Proxy({}, {
  get: (_, name) => (useLaravel(LARAVEL_ENTITIES, name) ? laravelEntity(name) : legacy.entities[name]),
});

const functions = {
  invoke: (name, payload) =>
    useLaravel(LARAVEL_FUNCTIONS, name) ? api('POST', `/functions/${name}`, { body: payload || {} }) : legacy.functions.invoke(name, payload),
};

const auth = {
  // Så lenge innlogging skjer i Base44, hentes bruker derfra; har vi Laravel-token brukes det.
  me: () => (apiToken.get() ? api('GET', '/auth/me') : legacy.auth.me()),
  login: async (email, password) => {
    const r = await api('POST', '/auth/login', { body: { email, password } });
    apiToken.set(r.token);
    return r.user;
  },
  logout: async (...args) => {
    if (apiToken.get()) { await api('POST', '/auth/logout').catch(() => {}); apiToken.clear(); }
    return legacy.auth.logout?.(...args);
  },
  isAuthenticated: () => !!apiToken.get() || legacy.auth.isAuthenticated?.(),
  redirectToLogin: (...a) => legacy.auth.redirectToLogin?.(...a),
};

export const base44 = { ...legacy, entities, functions, auth, integrations: legacy.integrations };
