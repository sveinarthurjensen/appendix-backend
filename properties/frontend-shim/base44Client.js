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
// Entiteter går bare mot Laravel når en ansatt har Laravel-sesjon – anonyme besøkende (offentlig portal) leser fortsatt fra Base44.
const hasSession = () => { try { return !!localStorage.getItem('appendix_oidc_tokens'); } catch { return false; } };

// ---------- Innlogging mot Laravel: OIDC authorization code + PKCE (public client) ----------
// Brukeren logger inn hos api.appendixholding.no (Entra/BankID). Har de allerede sesjon der, går det i ett hopp.
const CLIENT_ID = import.meta.env.VITE_OIDC_CLIENT_ID || 'appendix-properties-spa';
const TOK = 'appendix_oidc_tokens';   // {access, refresh, exp}
const PKCE = 'appendix_oidc_pkce';    // sessionStorage {verifier,state,back}
const rd = (st, k) => { try { return JSON.parse(st.getItem(k)); } catch { return null; } };
const wr = (st, k, v) => { try { st.setItem(k, JSON.stringify(v)); } catch { /* privat modus */ } };
const b64url = (buf) => btoa(String.fromCharCode(...new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const rand = (n = 32) => b64url(crypto.getRandomValues(new Uint8Array(n)));

export const apiToken = {
  get: () => { const t = rd(localStorage, TOK); return t && t.exp > Date.now() + 30000 ? t.access : null; },
  clear: () => { try { localStorage.removeItem(TOK); } catch { /* */ } },
};

const STEP = 'appendix_step_up_at';
const stepUpAllowed = () => { try { return Date.now() - Number(sessionStorage.getItem(STEP) || 0) > 60 * 1000; } catch { return false; } };
const markStepUp = () => { try { sessionStorage.setItem(STEP, String(Date.now())); } catch { /* */ } };

// ---------- Engangskode (SMS/e-post) for eksterne brukere – begrenset nivå ----------
// Krever at serveren har klienten i OTP_CLIENT_IDS. Gir token med nivå «otp» (ikke BankID).
export const otpLogin = {
  async request(channel, target) {
    const res = await fetch(LARAVEL_URL + '/api/auth/otp/request', {
      method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ channel, target, client_id: CLIENT_ID }),
    });
    const d = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(d.message || `${res.status}`), { status: res.status });
    return d; // {message, expires_in?}
  },
  async verify(channel, target, code) {
    const res = await fetch(LARAVEL_URL + '/api/auth/otp/verify', {
      method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ channel, target, code, client_id: CLIENT_ID }),
    });
    const t = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(new Error(t.message || 'Ugyldig eller utløpt kode'), { status: res.status });
    wr(localStorage, TOK, { access: t.access_token, refresh: t.refresh_token, exp: Date.now() + (t.expires_in || 3600) * 1000 });
    return true;
  },
};

async function tokenRequest(params) {
  const res = await fetch(LARAVEL_URL + '/oidc/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
    body: new URLSearchParams({ client_id: CLIENT_ID, ...params }),
  });
  if (!res.ok) throw new Error('token ' + res.status);
  const t = await res.json();
  wr(localStorage, TOK, { access: t.access_token, refresh: t.refresh_token || rd(localStorage, TOK)?.refresh, exp: Date.now() + (t.expires_in || 3600) * 1000 });
}

const TRIED = 'appendix_oidc_tried';
const recentlyTried = () => { try { return Date.now() - Number(sessionStorage.getItem(TRIED) || 0) < 10 * 60 * 1000; } catch { return true; } };
const markTried = () => { try { sessionStorage.setItem(TRIED, String(Date.now())); } catch { /* */ } };

async function startLogin(extra = {}) {
  markTried();
  const verifier = rand(48), state = rand(16);
  const challenge = b64url(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier)));
  wr(sessionStorage, PKCE, { verifier, state, back: location.pathname + location.search + location.hash });
  const u = new URL(LARAVEL_URL + '/oidc/authorize');
  Object.entries({ response_type: 'code', client_id: CLIENT_ID, redirect_uri: location.origin + '/', scope: 'openid profile email',
    state, code_challenge: challenge, code_challenge_method: 'S256', ...extra }).forEach(([k, v]) => u.searchParams.set(k, v));
  location.assign(u.toString());
  return new Promise(() => {}); // siden forlates
}

// Fanger opp ?code=&state= når vi kommer tilbake fra innlogging
let callbackDone = Promise.resolve();
(function handleCallback() {
  if (!LARAVEL_URL || typeof window === 'undefined') return;
  const q = new URLSearchParams(location.search);
  const code = q.get('code'), state = q.get('state'), p = rd(sessionStorage, PKCE);
  if (!p || p.state !== state) return;
  if (!code) { // access_denied o.l. – bli værende på Base44
    if (q.get('error')) { markTried(); try { sessionStorage.removeItem(PKCE); } catch { /* */ } history.replaceState({}, '', p.back || '/'); }
    return;
  }
  callbackDone = tokenRequest({ grant_type: 'authorization_code', code, redirect_uri: location.origin + '/', code_verifier: p.verifier })
    .then(() => { try { sessionStorage.removeItem(PKCE); } catch { /* */ } history.replaceState({}, '', p.back || '/'); })
    .catch(() => { apiToken.clear(); });
})();

async function ensureToken() {
  await callbackDone;
  const t = apiToken.get();
  if (t) return t;
  const stored = rd(localStorage, TOK);
  if (stored?.refresh) {
    try { await tokenRequest({ grant_type: 'refresh_token', refresh_token: stored.refresh }); return apiToken.get(); } catch { apiToken.clear(); }
  }
  return startLogin();
}

async function api(method, path, { body, query } = {}) {
  const token = await ensureToken();
  const url = new URL(LARAVEL_URL + '/api' + path);
  if (query) Object.entries(query).forEach(([k, v]) => v !== undefined && url.searchParams.set(k, v));
  const res = await fetch(url, {
    method,
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (res.status === 401) { apiToken.clear(); try { localStorage.removeItem(TOK); } catch { /* */ } }
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    // Step-up: backend krever sterkere/ferskere innlogging (BankID/MFA). Ny innlogging med prompt=login,
    // deretter tilbake til samme side. Vokter mot løkke: bare ett forsøk per side/minutt.
    if (res.status === 403 && err.error === 'step_up_required' && stepUpAllowed()) {
      markStepUp();
      window.dispatchEvent(new CustomEvent('appendix:step-up', { detail: { required: err.required, max_age_minutes: err.max_age_minutes } }));
      return startLogin({ prompt: 'login' }); // forlater siden; handlingen kan gjentas etter retur
    }
    throw Object.assign(new Error(err.message || `${res.status} ${res.statusText}`), { status: res.status, data: err });
  }
  return res.status === 204 ? null : res.json();
}

const readWithFallback = async (call, fallback) => {
  try { return await call(); } catch (e) {
    if (e.status === undefined || e.status >= 500) { console.warn('Laravel utilgjengelig, leser fra Base44', e); return fallback(); }
    throw e;
  }
};

function laravelEntity(name) {
  const base = `/entities/${name}`;
  const old = () => legacy.entities[name];
  return {
    list: (sort, limit, skip, fields) => readWithFallback(() => api('GET', base, { query: { sort, limit, skip, fields: fields?.join(',') } }), () => old().list(sort, limit, skip, fields)),
    filter: (q, sort, limit, skip, fields) => readWithFallback(() => api('GET', base, { query: { q: JSON.stringify(q || {}), sort, limit, skip, fields: fields?.join(',') } }), () => old().filter(q, sort, limit, skip, fields)),
    get: (id) => readWithFallback(() => api('GET', `${base}/${id}`), () => old().get(id)),
    create: (data) => api('POST', base, { body: data }),
    bulkCreate: (rows) => api('POST', base, { body: rows }),
    update: (id, data) => api('PATCH', `${base}/${id}`, { body: data }),
    delete: (id) => api('DELETE', `${base}/${id}`),
  };
}

const entities = new Proxy({}, {
  get: (_, name) => (useLaravel(LARAVEL_ENTITIES, name) && hasSession() ? laravelEntity(name) : legacy.entities[name]),
});

const functions = {
  ...legacy.functions,
  invoke: (name, payload) =>
    useLaravel(LARAVEL_FUNCTIONS, name) ? api('POST', `/functions/${name}`, { body: payload || {} }) : legacy.functions.invoke(name, payload),
};

const auth = {
  ...legacy.auth, // behold alle SDK-metoder (innlogging, updateMe, setToken …); under overstyres bare me/logout
  // Base44 eier fortsatt appens egen innlogging; Laravel-token brukes bare mot Laravel-entiteter.
  me: async () => {
    const u = await legacy.auth.me();
    if (u && LARAVEL_URL && LARAVEL_ENTITIES.size && !hasSession() && !recentlyTried()) startLogin(); // ett hopp via felles SSO-sesjon
    return u;
  },
  logout: async (...args) => {
    const t = apiToken.get();
    if (t) { await fetch(LARAVEL_URL + '/api/auth/logout', { method: 'POST', headers: { Authorization: `Bearer ${t}`, Accept: 'application/json' } }).catch(() => {}); }
    apiToken.clear();
    return legacy.auth.logout?.(...args);
  },
};

// Proxy i stedet for spread: SDK-klienten har getteren asServiceRole som kaster i nettleseren hvis den leses ({...legacy} krasjet siden).
const overrides = { entities, functions, auth };
export const base44 = new Proxy(legacy, {
  get: (target, key, receiver) => (key in overrides ? overrides[key] : Reflect.get(target, key, receiver)),
  has: (target, key) => key in overrides || key in target,
});
