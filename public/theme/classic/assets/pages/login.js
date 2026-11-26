import { h } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, setAuthData, isLoggedIn, clearCaches, fetchUser } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import { field, input, toast, button, loadingBlock } from '../lib/ui.js';
import { authPage, authLink, normalizeRedirect, applyAuthPayload } from './auth.js';

function verifyToken(el, query) {
    el.replaceChildren(authPage(h('div', {},
        loadingBlock(t('正在登录...'))
    )));
    api.get('/passport/auth/token2Login', { verify: query.verify })
        .then(payload => {
            applyAuthPayload(payload);
            setAuthData(payload.data.auth_data);
            clearCaches();
            fetchUser(true).catch(() => {});
            navigate(normalizeRedirect(query.redirect) || '/dashboard', { replace: true });
        })
        .catch(error => {
            toast(error.message || t('登录失败'), 'error');
            navigate('/login', { replace: true });
        });
}

export async function mount(el, ctx) {
    const query = ctx.query || {};
    if (query.verify) {
        verifyToken(el, query);
        return;
    }
    if (isLoggedIn()) {
        navigate('/dashboard', { replace: true });
        return;
    }

    const redirect = normalizeRedirect(query.redirect);
    const emailInput = input({
        type: 'email',
        name: 'email',
        autocomplete: 'email',
        placeholder: t('请输入邮箱'),
        value: query.email || ''
    });
    const passwordInput = input({
        type: 'password',
        name: 'password',
        autocomplete: 'current-password',
        placeholder: t('请输入密码')
    });
    const submit = button(t('登入'), { type: 'primary', size: 'lg', block: true });

    const form = h('form', {
        onSubmit: async event => {
            event.preventDefault();
            const email = emailInput.value.trim();
            const password = passwordInput.value;
            if (!email) {
                toast(t('请输入邮箱'), 'error');
                emailInput.focus();
                return;
            }
            if (!password) {
                toast(t('请输入密码'), 'error');
                passwordInput.focus();
                return;
            }
            submit.disabled = true;
            const original = submit.innerHTML;
            submit.textContent = t('登录中...');
            try {
                const payload = await api.post('/passport/auth/login', { email, password });
                applyAuthPayload(payload);
                setAuthData(payload.data.auth_data);
                clearCaches();
                fetchUser(true).catch(() => {});
                toast(t('登录成功'));
                navigate(redirect || '/dashboard', { replace: true });
            } catch (error) {
                toast(error.message || t('登录失败'), 'error', 3000);
                submit.disabled = false;
                submit.innerHTML = original;
            }
        }
    },
        field({ label: t('邮箱'), control: emailInput, required: true }),
        field({ label: t('密码'), control: passwordInput, required: true }),
        h('div', { class: 'auth-links' },
            authLink(t('忘记密码？'), '/forgetpassword'),
            authLink(t('还没有账号？注册'), redirect ? `/register?redirect=${encodeURIComponent(redirect)}` : '/register')
        ),
        submit
    );

    el.replaceChildren(authPage(h('div', {}, form)));
}
