import { h, money, formatDate, copyText } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, fetchCommConfig } from '../lib/api.js';
import {
    alert, button, card, confirmDialog, emptyBlock, errorBlock, field, input, loadingBlock,
    modal, pagination, select, table, toast
} from '../lib/ui.js';

function inviteLink(code) {
    const base = window.location.href.split('#')[0];
    return `${base}#/register?code=${encodeURIComponent(code)}`;
}

function transferModal(available, onDone) {
    const amountInput = input({
        type: 'number',
        min: '1',
        step: '0.01',
        placeholder: t('请输入金额')
    });
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
            field({ label: t('划转金额'), control: amountInput, hint: `${t('可用佣金')}: ${money(available)}` })
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}

function generateModal(onDone) {
    const codeInput = input({
        type: 'text',
        maxlength: '32',
        autocomplete: 'off',
        placeholder: t('请输入邀请码')
    });

    const submit = button(t('生成'), {
        type: 'primary',
        onClick: async () => {
            const code = codeInput.value.trim();
            if (code && !/^[A-Za-z0-9]{4,32}$/.test(code)) {
                toast(t('请输入4位以上的字母或数字，留空将随机生成'), 'error');
                codeInput.focus();
                return;
            }
            submit.disabled = true;
            try {
                await api.post('/user/invite/save', code ? { code } : {});
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
        title: t('生成邀请码'),
        body: h('div', {},
            field({
                label: t('邀请码'),
                control: codeInput,
                hint: t('请输入4位以上的字母或数字，留空将随机生成')
            })
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}

function withdrawModal(config, onDone) {
    const methods = (config && config.withdraw_methods) || [];
    const methodSelect = select(methods.map(method => ({ value: method, label: method })), methods[0] || '', null);
    const accountInput = input({ placeholder: t('请输入提现账号') });
    const submit = button(t('确认'), {
        type: 'primary',
        onClick: async () => {
            if (!methodSelect.value) {
                toast(t('请选择提现方式'), 'error');
                return;
            }
            if (!accountInput.value.trim()) {
                toast(t('请输入提现账号'), 'error');
                accountInput.focus();
                return;
            }
            submit.disabled = true;
            try {
                await api.post('/user/ticket/withdraw', {
                    withdraw_method: methodSelect.value,
                    withdraw_account: accountInput.value.trim()
                });
                toast(t('已提交提现申请'));
                controller.close();
                onDone();
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
                submit.disabled = false;
            }
        }
    });
    const controller = modal({
        title: t('佣金提现'),
        body: h('div', {},
            field({ label: t('提现方式'), control: methodSelect, required: true }),
            field({ label: t('提现账号'), control: accountInput, required: true })
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());
    const state = { current: 1, pageSize: 10 };

    const render = async () => {
        let invite = null;
        let config = {};
        let details = [];
        let total = 0;

        try {
            const [inviteResponse, configResponse, detailResponse] = await Promise.all([
                api.get('/user/invite/fetch'),
                fetchCommConfig(),
                api.get('/user/invite/details', { current: state.current, page_size: state.pageSize })
            ]);
            invite = inviteResponse ? inviteResponse.data : null;
            config = configResponse || {};
            details = detailResponse ? (detailResponse.data || []) : [];
            total = detailResponse ? Number(detailResponse.total || 0) : 0;
        } catch (error) {
            el.replaceChildren(errorBlock(error, () => mount(el)));
            return;
        }

        const stat = (invite && invite.stat) || [0, 0, 0, 0, 0];
        const codes = (invite && invite.codes) || [];
        const reload = () => {
            state.current = 1;
            render();
        };

        const stats = h('div', { class: 'stat-grid' },
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('已邀请用户数')),
                h('div', { class: 'sc-value' }, String(stat[0] || 0))
            ),
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('累计佣金')),
                h('div', { class: 'sc-value' }, money(stat[1] || 0))
            ),
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('佣金比例')),
                h('div', { class: 'sc-value' }, `${stat[3] || 0}%`)
            ),
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('可用佣金')),
                h('div', { class: 'sc-value' }, money(stat[4] || 0)),
                h('div', { class: 'sc-actions' },
                    button(t('划转'), {
                        size: 'sm',
                        onClick: () => transferModal(Number(stat[4] || 0), reload)
                    }),
                    Number(config.withdraw_close) === 1 ? null : button(t('提现'), {
                        size: 'sm',
                        onClick: () => withdrawModal(config, reload)
                    })
                )
            )
        );

        const codeItems = codes.length
            ? h('div', { class: 'code-list' }, codes.map(code => h('div', { class: 'code-chip' },
                h('span', { class: 'code-text' }, code.code),
                h('div', { class: 'code-actions' },
                    button(t('复制链接'), {
                        size: 'sm',
                        type: 'primary',
                        title: t('复制链接'),
                        onClick: async () => {
                            const ok = await copyText(inviteLink(code.code));
                            toast(ok ? t('已复制到剪贴板') : t('复制失败，请手动复制'), ok ? 'success' : 'error');
                        }
                    }),
                    button(t('删除'), {
                        size: 'sm',
                        type: 'danger',
                        onClick: async () => {
                            const ok = await confirmDialog({
                                title: t('删除邀请码'),
                                content: t('确认删除该邀请码？删除后该邀请码将无法使用。'),
                                okText: t('删除'),
                                danger: true
                            });
                            if (!ok) return;
                            try {
                                await api.post('/user/invite/delete', { id: code.id });
                                toast(t('操作成功'));
                                reload();
                            } catch (error) {
                                toast(error.message || t('请求失败'), 'error');
                            }
                        }
                    })
                )
            )))
            : h('div', { class: 'muted' }, t('还没有邀请码，点击右侧生成'));

        const detailsTable = table({
            columns: [
                { title: t('订单号'), render: row => h('span', { class: 'mono' }, row.trade_no) },
                { title: t('订单金额'), width: '120px', className: 'nowrap', cellClass: 'nowrap', render: row => money(row.order_amount) },
                { title: t('佣金'), width: '120px', className: 'nowrap', cellClass: 'nowrap', render: row => h('span', { class: 'tag tag-success' }, money(row.get_amount)) },
                { title: t('完成时间'), width: '170px', className: 'nowrap', cellClass: 'nowrap', render: row => formatDate(row.created_at, true) }
            ],
            rows: details,
            rowKey: 'id',
            empty: emptyBlock({ iconName: 'wallet', title: t('暂无记录') })
        });

        const detailCard = card({
            title: t('佣金发放记录'),
            extra: h('div', { class: 'small muted' },
                t('确认中的佣金'),
                h('span', { class: 'strong', style: { marginLeft: '6px' } }, money(stat[2] || 0))
            ),
            flush: true,
            body: h('div', {}, detailsTable, total > state.pageSize ? pagination({
                current: state.current,
                total,
                pageSize: state.pageSize,
                onChange: page => {
                    state.current = page;
                    render();
                }
            }) : null)
        });

        const nodes = [
            h('div', {},
                h('h1', { class: 'page-title' }, t('我的邀请')),
                h('div', { class: 'page-desc' }, t('邀请码管理'))
            ),
            stats,
            card({
                title: t('邀请码管理'),
                extra: button(t('生成邀请码'), {
                    size: 'sm',
                    type: 'primary',
                    iconName: 'plus',
                    onClick: () => generateModal(reload)
                }),
                body: h('div', {},
                    h('div', { class: 'field-hint mb-12' }, t('佣金将会在确认后会到达你的佣金账户。')),
                    codeItems,
                    h('div', { class: 'field-hint mt-12' }, t('将邀请链接分享给朋友，注册后即可获得佣金。'))
                )
            })
        ];

        if (config.commission_distribution_enable) {
            nodes.push(alert('info', t('三级分销比例'),
                `${t('一级')}: ${config.commission_distribution_l1 || 0}% · ${t('二级')}: ${config.commission_distribution_l2 || 0}% · ${t('三级')}: ${config.commission_distribution_l3 || 0}%`));
        }

        nodes.push(detailCard);

        el.replaceChildren(h('div', { class: 'stack' }, nodes));
    };

    await render();
}
