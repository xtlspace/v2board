import { h } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, setAuthData, isLoggedIn, clearCaches, fetchUser, fetchGuestConfig } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import { field, input, toast, button, loadingBlock } from '../lib/ui.js';
import { authPage, authLink, sendCodeButton, mountRecaptcha, normalizeRedirect, applyAuthPayload } from './auth.js';

export async function mount(el, ctx) {
    const query = ctx.query || {};
    if (isLoggedIn()) {
        navigate('/dashboard', { replace: true });
        return;
    }

    el.replaceChildren(authPage(loadingBlock()));

    let guest = {};
    try {
        guest = (await fetchGuestConfig()) || {};
    } catch {
        /* keep sensible defaults */
    }

    const redirect = normalizeRedirect(query.redirect);
    const needEmailVerify = Number(guest.is_email_verify) === 1;
    const inviteForce = Number(guest.is_invite_force) === 1;
    const tosUrl = guest.tos_url || '';

    const emailInput = input({
        type: 'email',
        name: 'email',
        autocomplete: 'email',
        placeholder: t('请输入邮箱'),
        value: query.email || ''
    });
    const codeInput = needEmailVerify ? input({
        type: 'text',
        name: 'email_code',
        inputmode: 'numeric',
        maxlength: '6',
        autocomplete: 'one-time-code',
        placeholder: t('请输入邮箱验证码')
    }) : null;
    const passwordInput = input({
        type: 'password',
        name: 'password',
        autocomplete: 'new-password',
        placeholder: t('请输入密码')
    });
    const confirmInput = input({
        type: 'password',
        name: 'confirm_password',
        autocomplete: 'new-password',
        placeholder: t('请再次输入密码')
    });
    const inviteInput = input({
        type: 'text',
        name: 'invite_code',
        placeholder: inviteForce ? t('请输入邀请码') : t('邀请码(选填)'),
        value: query.code || ''
    });

    const recaptchaBox = h('div', { class: 'mb-16' });
    let getRecaptchaToken = null;
    if (Number(guest.is_recaptcha) === 1 && guest.recaptcha_site_key) {
        getRecaptchaToken = await mountRecaptcha(recaptchaBox, guest.recaptcha_site_key);
    }

    const tosCheckbox = tosUrl ? h('label', { class: 'checkbox mb-16' },
        h('input', { type: 'checkbox' }),
        h('span', {}, t('我同意')),
        h('a', { href: tosUrl, target: '_blank', rel: 'noopener noreferrer' }, t('服务条款'))
    ) : null;

    const submit = button(t('注册'), { type: 'primary', size: 'lg', block: true });

    const form = h('form', {
        onSubmit: async event => {
            event.preventDefault();
            const email = emailInput.value.trim();
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                toast(t('请输入正确的邮箱'), 'error');
                emailInput.focus();
                return;
            }
            if (needEmailVerify && !codeInput.value.trim()) {
                toast(t('请输入邮箱验证码'), 'error');
                codeInput.focus();
                return;
            }
            if (!password || password.length < 8) {
                toast(t('密码至少 8 位'), 'error');
                passwordInput.focus();
                return;
            }
            if (password !== confirm) {
                toast(t('两次密码输入不同'), 'error');
                confirmInput.focus();
                return;
            }
            if (inviteForce && !inviteInput.value.trim()) {
                toast(t('请输入邀请码'), 'error');
                inviteInput.focus();
                return;
            }
            if (tosCheckbox && !tosCheckbox.querySelector('input').checked) {
                toast(t('请同意服务条款'), 'error');
                return;
            }
            let recaptchaData = '';
            if (getRecaptchaToken) {
                recaptchaData = getRecaptchaToken();
                if (!recaptchaData) {
                    toast(t('请完成人机验证'), 'error');
                    return;
                }
            }

            submit.disabled = true;
            const original = submit.innerHTML;
            submit.textContent = t('注册中...');
            try {
                const payload = await api.post('/passport/auth/register', {
                    email,
                    password,
                    email_code: codeInput ? codeInput.value.trim() : undefined,
                    invite_code: inviteInput.value.trim() || undefined,
                    recaptcha_data: recaptchaData || undefined
                });
                applyAuthPayload(payload);
                setAuthData(payload.data.auth_data);
                clearCaches();
                fetchUser(true).catch(() => {});
                toast(t('注册成功'));
                navigate(redirect || '/dashboard', { replace: true });
            } catch (error) {
                toast(error.message || t('注册失败'), 'error', 3000);
                submit.disabled = false;
                submit.innerHTML = original;
            }
        }
    },
        field({ label: t('邮箱'), control: emailInput, required: true }),
        codeInput ? field({
            label: t('邮箱验证码'),
            control: h('div', { class: 'input-group' },
                codeInput,
                sendCodeButton(emailInput, false)
            ),
            required: true
        }) : null,
        h('div', { class: 'form-inline' },
            field({ label: t('密码'), control: passwordInput, required: true, hint: t('密码至少 8 位') }),
            field({ label: t('确认密码'), control: confirmInput, required: true })
        ),
        field({
            label: inviteForce ? t('邀请码') : t('邀请码(选填)'),
            control: inviteInput,
            required: inviteForce
        }),
        Number(guest.is_recaptcha) === 1 ? recaptchaBox : null,
        tosCheckbox,
        submit,
        h('div', { class: 'auth-footer' },
            authLink(t('已有账号？登录'), redirect ? `/login?redirect=${encodeURIComponent(redirect)}` : '/login')
        )
    );

    el.replaceChildren(authPage(h('div', {}, form)));
}
