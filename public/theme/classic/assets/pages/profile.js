import { h, money, formatDate } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import {
    api, fetchUser, fetchSubscribe, fetchCommConfig, subscribeUrlFrom, clearCaches, getAuthData, logout
} from '../lib/api.js';
import { navigate } from '../lib/router.js';
import {
    alert, button, card, confirmDialog, copyButton, emptyBlock, errorBlock, field, input,
    loadingBlock, modal, tag, toast, switchRow
} from '../lib/ui.js';

function rechargeModal(balance) {
    const amountInput = input({
        type: 'number',
        min: '1',
        step: '0.01',
        placeholder: t('请输入充值金额')
    });
    const submit = button(t('确认充值'), {
        type: 'primary',
        onClick: async () => {
            const value = Number(amountInput.value);
            if (!value || value <= 0) {
                toast(t('请输入充值金额'), 'error');
                amountInput.focus();
                return;
            }
            const cents = Math.round(value * 100);
            if (cents >= 9999999) {
                toast(t('充值金额过大'), 'error');
                return;
            }
            submit.disabled = true;
            try {
                const response = await api.post('/user/order/save', {
                    plan_id: 0,
                    period: 'deposit',
                    deposit_amount: cents
                });
                const tradeNo = response ? response.data : '';
                controller.close();
                if (tradeNo) navigate(`/order/${tradeNo}`);
                else toast(t('订单已创建'));
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
                submit.disabled = false;
            }
        }
    });
    const controller = modal({
        title: t('充值余额'),
        body: h('div', {},
            h('p', { class: 'muted' }, t('充值金额将进入账户余额，可用于购买订阅。')),
            field({ label: t('充值金额'), control: amountInput, required: true }),
            field({
                label: t('当前余额'),
                control: h('div', { class: 'plan-name', style: { fontSize: '22px', margin: 0 } }, money(balance || 0))
            })
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}

function welcomeBalance(user) {
    let autoRenewal = Number(user && user.auto_renewal) === 1;
    const autoRenewalLabel = () => autoRenewal ? t('自动续费(开)') : t('自动续费(关)');
    const autoRenewalBtn = button(autoRenewalLabel(), {
        onClick: async () => {
            const next = !autoRenewal;
            autoRenewalBtn.disabled = true;
            try {
                await api.post('/user/update', { auto_renewal: next ? 1 : 0 });
                autoRenewal = next;
                if (user) user.auto_renewal = next ? 1 : 0;
                autoRenewalBtn.textContent = autoRenewalLabel();
                autoRenewalBtn.classList.toggle('btn-primary', autoRenewal);
                toast(autoRenewal ? t('已开启自动续费') : t('已关闭自动续费'));
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
            } finally {
                autoRenewalBtn.disabled = false;
            }
        }
    });
    if (autoRenewal) autoRenewalBtn.classList.add('btn-primary');

    return card({
        title: t('我的钱包'),
        body: h('div', {},
            h('div', { class: 'stat-grid' },
                h('div', { class: 'stat-card' },
                    h('div', { class: 'sc-label' }, t('账户余额(仅消费)')),
                    h('div', { class: 'sc-value' }, money(user ? user.balance : 0))
                ),
                h('div', { class: 'stat-card' },
                    h('div', { class: 'sc-label' }, t('推广佣金(可提现)')),
                    h('div', { class: 'sc-value' }, money(user ? user.commission_balance : 0))
                )
            ),
            h('div', { class: 'btn-group mt-16' },
                button(t('充值'), {
                    type: 'primary',
                    iconName: 'credit-card',
                    onClick: () => rechargeModal(user ? Number(user.balance) : 0)
                }),
                button(t('划转佣金'), {
                    iconName: 'wallet',
                    onClick: () => transferModal(user ? Number(user.commission_balance) : 0, () => refreshAll())
                }),
                autoRenewalBtn
            )
        )
    });
}

function giftcardCard(onDone) {
    const code = input({ placeholder: t('请输入礼品卡') });
    const submit = button(t('兑换'), {
        type: 'primary',
        onClick: async () => {
            const value = code.value.trim();
            if (!value) {
                toast(t('请输入礼品卡'), 'error');
                code.focus();
                return;
            }
            submit.disabled = true;
            try {
                await api.post('/user/redeemgiftcard', { giftcard: value });
                toast(t('兑换成功'));
                code.value = '';
                onDone();
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
            } finally {
                submit.disabled = false;
            }
        }
    });
    return card({
        title: t('兑换礼品卡'),
        body: h('div', {},
            h('div', { class: 'input-group' }, code, submit),
            h('div', { class: 'field-hint mt-8' }, t('输入礼品卡兑换码'))
        )
    });
}

function passwordCard(onChanged) {
    const oldPassword = input({ type: 'password', autocomplete: 'current-password', placeholder: t('请输入旧密码') });
    const newPassword = input({ type: 'password', autocomplete: 'new-password', placeholder: t('请输入新密码') });
    const confirmPassword = input({ type: 'password', autocomplete: 'new-password', placeholder: t('请再次输入密码') });
    const submit = button(t('修改密码'), {
        type: 'primary',
        onClick: async () => {
            if (!oldPassword.value) {
                toast(t('请输入旧密码'), 'error');
                oldPassword.focus();
                return;
            }
            if (!newPassword.value || newPassword.value.length < 8) {
                toast(t('密码至少 8 位'), 'error');
                newPassword.focus();
                return;
            }
            if (newPassword.value !== confirmPassword.value) {
                toast(t('两次新密码输入不同'), 'error');
                confirmPassword.focus();
                return;
            }
            submit.disabled = true;
            try {
                await api.post('/user/changePassword', {
                    old_password: oldPassword.value,
                    new_password: newPassword.value
                });
                toast(t('修改成功，请重新登录'));
                logout();
                onChanged();
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
                submit.disabled = false;
            }
        }
    });
    return card({
        title: t('修改密码'),
        body: h('div', {},
            field({ label: t('旧密码'), control: oldPassword, required: true }),
            field({ label: t('新密码'), control: newPassword, required: true, hint: t('密码至少 8 位') }),
            field({ label: t('确认新密码'), control: confirmPassword, required: true }),
            submit
        )
    });
}

function notificationCard(user, onSaved) {
    const state = {
        remind_expire: Number(user && user.remind_expire) === 1,
        remind_traffic: Number(user && user.remind_traffic) === 1
    };
    const save = async (key, value) => {
        state[key] = value;
        try {
            await api.post('/user/update', {
                remind_expire: state.remind_expire ? 1 : 0,
                remind_traffic: state.remind_traffic ? 1 : 0
            });
            toast(t('已保存'));
            if (onSaved) onSaved(state);
        } catch (error) {
            state[key] = !value;
            toast(error.message || t('请求失败'), 'error');
        }
    };
    return card({
        title: t('通知设置'),
        body: h('div', {},
            switchRow(t('到期邮件提醒'), '', state.remind_expire, value => save('remind_expire', value)),
            switchRow(t('流量邮件提醒'), '', state.remind_traffic, value => save('remind_traffic', value))
        )
    });
}

function resetCard(onReset) {
    return card({
        title: t('重置订阅信息'),
        body: h('div', {},
            h('p', { class: 'muted' }, t('重置订阅提示信息')),
            h('div', { class: 'btn-group mt-12' },
                button(t('重置订阅'), { type: 'danger', iconName: 'refresh', onClick: onReset })
            )
        )
    });
}

function sessionsCard(sessions, onChanged) {
    const rows = sessions.map(session => h('div', { class: 'session-item' },
        h('span', { class: 'session-icon' }, h('span', {}, '💻')),
        h('div', { class: 'session-main' },
            h('div', { class: 's-ua' }, session.ua || '-'),
            h('div', { class: 's-meta' },
                `${t('IP 地址')}: ${session.ip || '-'}`,
                ' · ',
                `${t('登录时间')}: ${formatDate(session.login_at, true)}`
            )
        ),
        session.is_current ? tag(t('当前会话'), 'primary') : button(t('移除'), {
            size: 'sm',
            onClick: async () => {
                const ok = await confirmDialog({
                    title: t('移除'),
                    content: t('确认'),
                    okText: t('移除'),
                    danger: true
                });
                if (!ok) return;
                try {
                    await api.post('/user/removeActiveSession', { session_id: session.session_id });
                    toast(t('操作成功'));
                    onChanged();
                } catch (error) {
                    toast(error.message || t('请求失败'), 'error');
                }
            }
        })
    ));

    return card({
        title: t('活跃会话'),
        body: rows.length ? h('div', {}, rows) : emptyBlock({ iconName: 'smartphone', title: t('暂无记录') })
    });
}

function telegramCard(user, botInfo, onChanged) {
    const bound = Boolean(user && user.telegram_id);
    const username = botInfo && botInfo.username ? botInfo.username : '';
    const link = username ? `https://t.me/${username}` : 'https://t.me/';

    if (bound) {
        return card({
            title: 'Telegram',
            body: h('div', {},
                alert('success', t('已绑定'), username ? `@${username}` : ''),
                h('div', { class: 'btn-group mt-16' },
                    button(t('解除绑定'), {
                        type: 'danger',
                        onClick: async () => {
                            const ok = await confirmDialog({
                                title: t('解除绑定'),
                                content: t('确认'),
                                okText: t('解除绑定'),
                                danger: true
                            });
                            if (!ok) return;
                            try {
                                await api.get('/user/unbindTelegram');
                                toast(t('操作成功'));
                                onChanged();
                            } catch (error) {
                                toast(error.message || t('请求失败'), 'error');
                            }
                        }
                    })
                )
            )
        });
    }

    return card({
        title: 'Telegram',
        body: h('div', {},
            h('p', { class: 'muted' }, t('绑定Telegram获取更多服务')),
            h('div', { class: 'muted', style: { lineHeight: '2' } },
                h('div', {}, `${t('第一步')}: ${t('打开Telegram搜索')} @${username || 'bot'}`),
                h('div', {}, `${t('第二步')}: ${t('向机器人发送 /bind 订阅地址 完成绑定')}`)
            ),            h('div', { class: 'btn-group mt-12' },
                h('a', { class: 'btn btn-primary', href: link, target: '_blank', rel: 'noopener noreferrer' }, t('打开 Telegram')),
                copyButton(subscribeUrlFrom(window.__subscribe) || '', t('复制订阅地址'), { size: '' })
            )
        )
    });
}

function transferModal(available, onDone) {
    const amountInput = input({ type: 'number', min: '1', step: '0.01', placeholder: t('请输入金额') });
    const submit = button(t('确认'), {
        type: 'primary',
        onClick: async () => {
            const value = Number(amountInput.value);
            if (!value || value <= 0) {
                toast(t('请输入金额'), 'error');
                return;
            }
            const cents = Math.round(value * 100);
            if (cents > available) {
                toast(t('可用佣金不足'), 'error');
                return;
            }
            submit.disabled = true;
            try {
                await api.post('/user/transfer', { transfer_amount: cents });
                toast(t('操作成功'));
                controller.close();
                onDone();
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
                submit.disabled = false;
            }
        }
    });
    const controller = modal({
        title: t('推广佣金划转至余额'),
        body: h('div', {},
            h('p', { class: 'muted' }, t('划转后的余额仅用于{title}消费使用', { title: window.settings.title || 'V2Board' })),
            field({ label: t('当前推广佣金余额'), control: h('div', { class: 'plan-name', style: { fontSize: '22px', margin: 0 } }, money(available)) }),
            field({ label: t('划转金额'), control: amountInput })
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}

function normalizeSessions(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw;
    const auth = getAuthData();
    return Object.keys(raw).map(key => {
        const item = raw[key] || {};
        return {
            session_id: key,
            ip: item.ip,
            ua: item.ua,
            login_at: item.login_at,
            is_current: Boolean(item.auth_data && auth && item.auth_data === auth)
        };
    }).sort((a, b) => Number(b.login_at || 0) - Number(a.login_at || 0));
}

let refreshHook = null;

function refreshAll() {
    if (typeof refreshHook === 'function') refreshHook();
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());
    refreshHook = () => mount(el);

    let user = null;
    let subscribe = null;
    let comm = {};
    let sessions = [];
    let botInfo = null;

    try {
        const results = await Promise.allSettled([
            fetchUser(true),
            fetchSubscribe(true),
            fetchCommConfig(),
            api.get('/user/getActiveSession'),
            api.get('/user/telegram/getBotInfo')
        ]);
        user = results[0].status === 'fulfilled' ? results[0].value : null;
        subscribe = results[1].status === 'fulfilled' ? results[1].value : null;
        comm = results[2].status === 'fulfilled' ? results[2].value : {};
        sessions = results[3].status === 'fulfilled' && results[3].value ? normalizeSessions(results[3].value.data) : [];
        botInfo = results[4].status === 'fulfilled' && results[4].value ? results[4].value.data : null;
    } catch (error) {
        el.replaceChildren(errorBlock(error, () => mount(el)));
        return;
    }

    window.__subscribe = subscribe;

    const onReset = async () => {
        const ok = await confirmDialog({
            title: t('确定要重置订阅信息？'),
            content: t('重置订阅提示信息'),
            okText: t('重置')
        });
        if (!ok) return;
        try {
            await api.get('/user/resetSecurity');
            clearCaches();
            toast(t('重置成功'));
            refreshAll();
        } catch (error) {
            toast(error.message || t('请求失败'), 'error');
        }
    };

    const nodes = [
        h('div', { class: 'flex-between flex-wrap' },
            h('div', {},
                h('h1', { class: 'page-title' }, t('个人中心'))
            )
        ),
        h('div', { class: 'grid grid-2 profile-pair' },
            welcomeBalance(user),
            giftcardCard(refreshAll)
        ),
        notificationCard(user),
        h('div', { class: 'grid grid-2' },
            passwordCard(() => navigate('/login')),
            resetCard(onReset)
        ),
        sessionsCard(sessions, refreshAll)
    ];

    if (Number(comm.is_telegram) === 1) {
        nodes.push(telegramCard(user, botInfo, refreshAll));
    }

    el.replaceChildren(h('div', { class: 'stack' }, nodes));
}
