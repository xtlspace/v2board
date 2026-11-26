import { mockRequest } from './mock.js';
import { setCurrencySymbol } from './dom.js';

const BASE = '/api/v1';
const AUTH_KEY = 'authorization';

export class ApiError extends Error {
    constructor(message, status = 0, payload = null) {
        super(message || 'Request failed');
        this.name = 'ApiError';
        this.status = status;
        this.payload = payload;
    }
}

export function getAuthData() {
    try {
        return localStorage.getItem(AUTH_KEY) || '';
    } catch {
        return '';
    }
}

export function setAuthData(value) {
    try {
        if (value) localStorage.setItem(AUTH_KEY, value);
        else localStorage.removeItem(AUTH_KEY);
    } catch {
        /* ignore private mode errors */
    }
}

export function isLoggedIn() {
    return Boolean(getAuthData());
}

export function logout() {
    setAuthData('');
    userCache = null;
    subscribeCache = null;
    commConfigCache = null;
}

function stringifyQuery(data) {
    const params = new URLSearchParams();
    Object.keys(data || {}).forEach(key => {
        const value = data[key];
        if (value === undefined || value === null || value === '') return;
        params.append(key, value);
    });
    const text = params.toString();
    return text ? `?${text}` : '';
}

function messageFromPayload(payload, status) {
    if (payload && typeof payload === 'object') {
        if (payload.errors && typeof payload.errors === 'object') {
            const first = Object.values(payload.errors)[0];
            if (Array.isArray(first) && first.length) return first[0];
            if (first) return String(first);
        }
        if (payload.message) return String(payload.message);
        if (payload.error) return String(payload.error);
    }
    return status ? `Request failed (${status})` : 'Request failed';
}

async function request(method, path, data) {
    if (window.settings && window.settings.mock) {
        return mockRequest(method, path, data || {});
    }

    const headers = { Accept: 'application/json' };
    const authorization = getAuthData();
    if (authorization) headers.authorization = authorization;

    let url = BASE + path;
    let body;
    if (method === 'GET' || method === 'DELETE') {
        url += stringifyQuery(data);
    } else {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data || {});
    }

    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            body,
            credentials: 'same-origin',
            cache: 'no-store'
        });
    } catch {
        const message = window.settings && window.settings.language === 'en-US'
            ? 'Network error, please try again later'
            : '网络错误，请稍后重试';
        throw new ApiError(message, 0, null);
    }

    let payload = null;
    const raw = await response.text();
    if (raw) {
        try {
            payload = JSON.parse(raw);
        } catch {
            payload = null;
        }
    }

    if (response.status === 403) {
        logout();
        if (!/^#\/login/.test(window.location.hash || '')) {
            const redirect = encodeURIComponent((window.location.hash || '#/dashboard').replace(/^#/, ''));
            window.location.hash = `/login?redirect=${redirect}`;
        }
        throw new ApiError(messageFromPayload(payload, 403), 403, payload);
    }

    if (!response.ok) {
        throw new ApiError(messageFromPayload(payload, response.status), response.status, payload);
    }

    return payload;
}

export const api = {
    get(path, params) {
        return request('GET', path, params);
    },
    post(path, data) {
        return request('POST', path, data);
    },
    del(path, data) {
        return request('DELETE', path, data);
    }
};

/* ---------------- cached user data ---------------- */

let userCache = null;
let subscribeCache = null;
let commConfigCache = null;

export async function fetchUser(force = false) {
    if (!force && userCache) return userCache;
    const response = await api.get('/user/info');
    userCache = response ? response.data : null;
    return userCache;
}

export function cachedUser() {
    return userCache;
}

export function clearCaches() {
    userCache = null;
    subscribeCache = null;
}

export async function fetchSubscribe(force = false) {
    if (!force && subscribeCache) return subscribeCache;
    const response = await api.get('/user/getSubscribe');
    subscribeCache = response ? response.data : null;
    return subscribeCache;
}

export async function fetchCommConfig(force = false) {
    if (!force && commConfigCache) return commConfigCache;
    const response = await api.get('/user/comm/config');
    commConfigCache = response ? response.data : null;
    if (commConfigCache && commConfigCache.currency_symbol) {
        setCurrencySymbol(commConfigCache.currency_symbol);
    }
    return commConfigCache;
}

let guestConfigCache = null;

export async function fetchGuestConfig(force = false) {
    if (!force && guestConfigCache) return guestConfigCache;
    const response = await api.get('/guest/comm/config');
    guestConfigCache = response ? response.data : null;
    return guestConfigCache;
}

export function subscribeUrlFrom(subscribe) {
    if (!subscribe) return '';
    if (subscribe.subscribe_url) return subscribe.subscribe_url;
    if (subscribe.token) return `${window.location.origin}/api/v1/client/subscribe?token=${subscribe.token}`;
    return '';
}
