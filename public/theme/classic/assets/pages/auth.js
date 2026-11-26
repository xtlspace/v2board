import { h } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api } from '../lib/api.js';
import { authBrand } from '../layout.js';
import { toast } from '../lib/ui.js';

export function authPage(content, settings = window.settings || {}, footer = null) {
    const background = h('div', {
        class: `auth-bg ${settings.background_url ? 'has-image' : ''}`.trim(),
        style: settings.background_url ? { backgroundImage: `url("${settings.background_url}")` } : {}
    });
    return h('div', { class: 'auth' },
        background,
        h('div', { class: 'auth-card' },
            h('div', { class: 'auth-card-body' },
                authBrand(settings),
                content
            ),
            footer ? h('div', { class: 'auth-card-foot' }, footer) : null
        )
    );
}

export function authLink(label, hash) {
    return h('a', { href: `#${hash}`, class: 'small' }, label);
}

export function sendCodeButton(emailInput, isForget = false) {
    let timer = null;
    let left = 0;
    const el = h('button', { class: 'btn', type: 'button' }, t('获取验证码'));

    const tick = () => {
        if (left <= 0) {
            el.disabled = false;
            el.textContent = t('获取验证码');
            window.clearInterval(timer);
            timer = null;
            return;
        }
        el.disabled = true;
        el.textContent = t('{seconds}s 后重发', { seconds: left });
    };

    el.addEventListener('click', async () => {
        const email = (emailInput.value || '').trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            toast(t('请输入正确的邮箱'), 'error');
            emailInput.focus();
            return;
        }
        el.disabled = true;
        try {
            await api.post('/passport/comm/sendEmailVerify', {
                email,
                isforget: isForget ? 1 : 0
            });
            toast(t('验证码已发送'));
            left = 60;
            tick();
            timer = window.setInterval(() => {
                left -= 1;
                tick();
            }, 1000);
        } catch (error) {
            el.disabled = false;
            toast(error.message || t('请求失败'), 'error');
        }
    });

    const originalRemove = el.remove.bind(el);
    el.remove = () => {
        if (timer) window.clearInterval(timer);
        originalRemove();
    };

    return el;
}

/* ---------------- reCAPTCHA ---------------- */

let recaptchaLoader = null;

function loadRecaptcha() {
    if (window.grecaptcha && window.grecaptcha.render) return Promise.resolve();
    if (recaptchaLoader) return recaptchaLoader;
    recaptchaLoader = new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-recaptcha]');
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('reCAPTCHA failed to load')));
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?render=explicit&hl=zh-CN';
        script.async = true;
        script.defer = true;
        script.dataset.recaptcha = '1';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('reCAPTCHA failed to load'));
        document.head.appendChild(script);
    });
    return recaptchaLoader;
}

export async function mountRecaptcha(container, siteKey) {
    if (!siteKey) return null;
    try {
        await loadRecaptcha();
        const widgetId = window.grecaptcha.render(container, { sitekey: siteKey });
        return () => window.grecaptcha.getResponse(widgetId) || '';
    } catch (error) {
        container.appendChild(h('div', { class: 'field-error' }, error.message));
        return null;
    }
}

export function normalizeRedirect(value) {
    if (!value) return '';
    let target = String(value);
    if (target.startsWith('#')) target = target.slice(1);
    if (!target.startsWith('/')) target = `/${target}`;
    return target;
}

export function applyAuthPayload(payload) {
    const data = payload && payload.data ? payload.data : null;
    if (!data || !data.auth_data) throw new Error(t('请求失败'));
    return data;
}
