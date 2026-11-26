/**
 * DOM + formatting helpers. All dynamic text goes through text nodes; only
 * trusted HTML (admin-authored plan / knowledge / notice content) is injected
 * with `raw`.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

const ICONS = {
    dashboard: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    server: '<rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><path d="M6 6.5h.01M6 17.5h.01"/>',
    receipt: '<path d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-2 2-2-2-2 2-3-2V3a1 1 0 0 1 1-1z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
    wallet: '<path d="M20 7H5a2 2 0 0 1 0-4h13v4"/><path d="M3 5v14a2 2 0 0 0 2 2h15V7"/><path d="M16 13h2"/>',
    ticket: '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M8 6v12M13 10h5M13 14h3"/>',
    chart: '<path d="M4 20V10M10 20V4M16 20v-6"/><path d="M2 20h20"/>',
    book: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    gift: '<rect x="3" y="8" width="18" height="4"/><path d="M5 12v9h14v-9M12 8v13"/><path d="M12 8S9.5 3 7 4.5 8 8 12 8zM12 8s2.5-5 5-3.5S16 8 12 8z"/>',
    copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
    check: '<path d="M20 6L9 17l-5-5"/>',
    'alert-circle': '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
    'alert-triangle': '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    menu: '<path d="M3 6h18M3 12h18M3 18h18"/>',
    x: '<path d="M18 6L6 18M6 6l12 12"/>',
    'chevron-down': '<path d="M6 9l6 6 6-6"/>',
    'chevron-right': '<path d="M9 6l6 6-6 6"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
    refresh: '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/>',
    qr: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM20 14h1M14 20h1M17 17h3v3h-3z"/>',
    send: '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    'credit-card': '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    smartphone: '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
    'arrow-left': '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    wifi: '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5.5 5.5 0 0 1 7 0M12 19.5h.01"/>'
};

export function icon(name, size = 16, extraClass = '') {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', String(size));
    svg.setAttribute('height', String(size));
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    if (extraClass) svg.setAttribute('class', extraClass);
    svg.innerHTML = ICONS[name] || ICONS.info;
    return svg;
}

function appendChildren(el, children) {
    for (const child of children.flat(Infinity)) {
        if (child === null || child === undefined || child === false) continue;
        if (Array.isArray(child)) {
            appendChildren(el, child);
            continue;
        }
        if (child instanceof Node) {
            el.appendChild(child);
        } else {
            el.appendChild(document.createTextNode(String(child)));
        }
    }
}

function applyAttrs(el, attrs) {
    if (!attrs) return;
    for (const key of Object.keys(attrs)) {
        const value = attrs[key];
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class' || key === 'className') {
            el.className = Array.isArray(value) ? value.filter(Boolean).join(' ') : String(value);
        } else if (key === 'style') {
            if (typeof value === 'string') el.style.cssText = value;
            else Object.assign(el.style, value);
        } else if (key === 'dataset') {
            Object.assign(el.dataset, value);
        } else if (key === 'html') {
            el.innerHTML = value;
        } else if (key === 'text') {
            el.textContent = value;
        } else if (key.startsWith('on') && typeof value === 'function') {
            el.addEventListener(key.slice(2).toLowerCase(), value);
        } else if (key === 'value') {
            el.value = value;
        } else if (key === 'checked' || key === 'disabled' || key === 'selected' || key === 'readOnly' || key === 'required') {
            el[key] = Boolean(value);
        } else {
            el.setAttribute(key, value === true ? '' : String(value));
        }
    }
}

export function h(tag, attrs, ...children) {
    const el = document.createElement(tag);
    applyAttrs(el, attrs);
    appendChildren(el, children);
    return el;
}

export function raw(html) {
    const div = document.createElement('div');
    div.innerHTML = html == null ? '' : String(html);
    return div;
}

/* ---------------- formatting ---------------- */

let currencySymbol = '\u00a5';

export function setCurrencySymbol(symbol) {
    if (symbol) currencySymbol = String(symbol);
}

export function money(cents, withSymbol = true) {
    const value = Number(cents || 0) / 100;
    const text = value.toFixed(2);
    return withSymbol ? `${currencySymbol}${text}` : text;
}

export function formatBytes(bytes) {
    const value = Number(bytes || 0);
    if (value <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    let index = 0;
    let size = value;
    while (size >= 1024 && index < units.length - 1) {
        size /= 1024;
        index += 1;
    }
    return `${size.toFixed(index === 0 ? 0 : 2)} ${units[index]}`;
}

function pad(n) {
    return n < 10 ? `0${n}` : String(n);
}

export function formatDate(ts, withTime = false) {
    if (!ts) return '-';
    const date = new Date(Number(ts) * 1000);
    if (Number.isNaN(date.getTime())) return '-';
    const y = date.getFullYear();
    const m = pad(date.getMonth() + 1);
    const d = pad(date.getDate());
    if (!withTime) return `${y}/${m}/${d}`;
    return `${y}/${m}/${d} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function formatLongDate(ts) {
    if (!ts) return '-';
    const date = new Date(Number(ts) * 1000);
    if (Number.isNaN(date.getTime())) return '-';
    const lang = document.documentElement.lang || 'zh-CN';
    if (lang.toLowerCase().startsWith('zh')) {
        return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
    }
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

export function nowSeconds() {
    return Math.floor(Date.now() / 1000);
}

export function daysBetween(target) {
    return Math.ceil((Number(target) - nowSeconds()) / 86400);
}

export function hoursBetween(target) {
    return Math.ceil((Number(target) - nowSeconds()) / 3600);
}

export function debounce(fn, wait = 300) {
    let timer = null;
    return function debounced(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
}

/* ---------------- clipboard ---------------- */

export async function copyText(value) {
    const content = String(value == null ? '' : value);
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(content);
            return true;
        }
    } catch {
        /* fall through to the legacy path */
    }
    try {
        const area = document.createElement('textarea');
        area.value = content;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(area);
        return ok;
    } catch {
        return false;
    }
}

/** Safe base64 (no padding) used by client URL schemes. */
export function base64Url(value) {
    const bytes = new TextEncoder().encode(String(value));
    let binary = '';
    bytes.forEach(byte => { binary += String.fromCharCode(byte); });
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
