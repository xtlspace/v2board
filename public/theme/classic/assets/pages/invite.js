import { h, money, formatDate, copyText } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, fetchCommConfig } from '../lib/api.js';
import {
    alert, button, card, emptyBlock, errorBlock, field, input, loadingBlock,
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
                h('div', { class: 'sc-label' }, t('已注册用户数')),
                h('div', { class: 'sc-value' }, String(stat[0] || 0))
            ),
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('累计佣金')),
                h('div', { class: 'sc-value' }, money(stat[1] || 0))
            ),
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('确认中的佣金')),
                h('div', { class: 'sc-value' }, money(stat[2] || 0))
            ),
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('佣金比例')),
                h('div', { class: 'sc-value' }, `${stat[3] || 0}%`)
            ),
            h('div', { class: 'stat-card' },
                h('div', { class: 'sc-label' }, t('可用佣金')),
                h('div', { class: 'sc-value' }, money(stat[4] || 0))
            )
        );

        const codeItems = codes.length
            ? h('div', { class: 'code-list' }, codes.map(code => h('div', { class: 'code-chip' },
                h('span', {}, code.code),
                h('span', { class: 'copy', title: t('复制链接'), onClick: async () => {
                    const ok = await copyText(inviteLink(code.code));
                    toast(ok ? t('已复制到剪贴板') : t('复制失败，请手动复制'), ok ? 'success' : 'error');
                } }, t('复制链接')),
                h('span', { class: 'faint' }, `PV ${code.pv || 0}`)
            )))
            : h('div', { class: 'muted' }, t('还没有邀请码，点击右侧生成'));

        const detailsTable = table({
            columns: [
                { title: t('订单号'), render: row => h('span', { class: 'mono' }, row.trade_no) },
                { title: t('订单金额'), width: '120px', render: row => money(row.order_amount) },
                { title: t('佣金'), width: '120px', render: row => h('span', { class: 'tag tag-success' }, money(row.get_amount)) },
                { title: t('完成时间'), width: '170px', className: 'nowrap', render: row => formatDate(row.created_at, true) }
            ],
            rows: details,
            rowKey: 'id',
            empty: emptyBlock({ iconName: 'wallet', title: t('暂无记录') })
        });

        const detailCard = card({
            title: t('佣金发放记录'),
            extra: h('div', { class: 'btn-group' },
                button(t('划转'), {
                    size: 'sm',
                    onClick: () => transferModal(Number(stat[4] || 0), reload)
                }),
                Number(config.withdraw_close) === 1 ? null : button(t('提现'), {
                    size: 'sm',
                    onClick: () => withdrawModal(config, reload)
                })
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
            h('div', { class: 'flex-between flex-wrap' },
                h('div', {},
                    h('h1', { class: 'page-title' }, t('我的邀请')),
                    h('div', { class: 'page-desc' }, t('邀请码管理'))
                ),
                h('div', { class: 'btn-group' },
                    button(t('生成邀请码'), {
                        type: 'primary',
                        iconName: 'plus',
                        onClick: async () => {
                            try {
                                await api.get('/user/invite/save');
                                toast(t('操作成功'));
                                render();
                            } catch (error) {
                                toast(error.message || t('请求失败'), 'error');
                            }
                        }
                    })
                )
            ),
            stats,
            card({
                title: t('邀请码管理'),
                extra: h('span', { class: 'small faint' }, t('佣金将会在确认后会到达你的佣金账户。')),
                body: h('div', {},
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
