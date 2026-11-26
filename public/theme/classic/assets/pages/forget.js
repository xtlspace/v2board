import { h } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import { field, input, toast, button } from '../lib/ui.js';
import { authPage, authLink, sendCodeButton } from './auth.js';

export async function mount(el) {
    const emailInput = input({
        type: 'email',
        name: 'email',
        autocomplete: 'email',
        placeholder: t('请输入邮箱')
    });
    const codeInput = input({
        type: 'text',
        name: 'email_code',
        inputmode: 'numeric',
        maxlength: '6',
        autocomplete: 'one-time-code',
        placeholder: t('请输入邮箱验证码')
    });
    const passwordInput = input({
        type: 'password',
        name: 'password',
        autocomplete: 'new-password',
        placeholder: t('请输入新密码')
    });
    const confirmInput = input({
        type: 'password',
        name: 'confirm_password',
        autocomplete: 'new-password',
        placeholder: t('请再次输入密码')
    });

    const submit = button(t('重置密码'), { type: 'primary', size: 'lg', block: true, submit: true });

    const form = h('form', {
        onSubmit: async event => {
            event.preventDefault();
            const email = emailInput.value.trim();
            const code = codeInput.value.trim();
            const password = passwordInput.value;
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                toast(t('请输入正确的邮箱'), 'error');
                emailInput.focus();
                return;
            }
            if (!/^\d{6}$/.test(code)) {
                toast(t('请输入邮箱验证码'), 'error');
                codeInput.focus();
                return;
            }
            if (!password || password.length < 8) {
                toast(t('密码至少 8 位'), 'error');
                passwordInput.focus();
                return;
            }
            if (password !== confirmInput.value) {
                toast(t('两次密码输入不同'), 'error');
                confirmInput.focus();
                return;
            }

            submit.disabled = true;
            const original = submit.innerHTML;
            submit.textContent = t('提交中...');
            try {
                await api.post('/passport/auth/forget', {
                    email,
                    email_code: code,
                    password
                });
                toast(t('重置成功'));
                navigate('/login', { replace: true });
            } catch (error) {
                toast(error.message || t('请求失败'), 'error', 3000);
                submit.disabled = false;
                submit.innerHTML = original;
            }
        }
    },
        field({ label: t('邮箱'), control: emailInput, required: true }),
        field({
            label: t('邮箱验证码'),
            control: h('div', { class: 'input-group' }, codeInput, sendCodeButton(emailInput, true)),
            required: true
        }),
        field({ label: t('新密码'), control: passwordInput, required: true, hint: t('密码至少 8 位') }),
        field({ label: t('确认密码'), control: confirmInput, required: true }),
        submit,
        h('div', { class: 'auth-footer' },
            authLink(t('返回登入'), '/login')
        )
    );

    el.replaceChildren(authPage(h('div', {}, form)));
}
