import { h, money, formatDate, formatBytes } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, fetchCommConfig } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import {
    alert, button, card, confirmDialog, emptyBlock, errorBlock, loadingBlock,
    modal, periodLabel, qrImage, statusTag, table, toast
} from '../lib/ui.js';

/* ---------------- order list ---------------- */

function orderActions(order, reload) {
    const nodes = [
        button(t('详情'), { size: 'sm', onClick: () => navigate(`/order/${order.trade_no}`) })
    ];
    if (Number(order.status) === 0) {
        nodes.push(button(t('取消'), {
            size: 'sm',
            onClick: async () => {
                const ok = await confirmDialog({
                    title: t('确认'),
                    content: t('如果你已经付款，取消订单可能会导致支付失败，确定取消订单吗？'),
                    okText: t('确定取消'),
                    danger: true
                });
                if (!ok) return;
                try {
                    await api.post('/user/order/cancel', { trade_no: order.trade_no });
                    toast(t('订单已取消'));
                    reload();
                } catch (error) {
                    toast(error.message || t('请求失败'), 'error');
                }
            }
        }));
    }
    return h('div', { class: 'cell-actions' }, nodes);
}

function ordersTable(orders, reload) {
    return table({
        columns: [
            { title: t('# 订单号'), width: '180px', render: order => h('span', { class: 'mono' }, order.trade_no) },
            { title: t('周期'), width: '120px', render: order => periodLabel(order.period) },
            { title: t('订单金额'), width: '120px', className: 'nowrap', cellClass: 'nowrap', render: order => money(order.total_amount) },
            { title: t('订单状态'), width: '110px', render: order => statusTag(order.status) },
            { title: t('创建时间'), width: '160px', className: 'nowrap', cellClass: 'nowrap', render: order => formatDate(order.created_at, true) },
            { title: t('操作'), width: '190px', render: order => orderActions(order, reload) }
        ],
        rows: orders,
        rowKey: 'trade_no',
        empty: emptyBlock({
            iconName: 'receipt',
            title: t('暂无数据')
        })
    });
}

export async function mountList(el) {
    el.replaceChildren(loadingBlock());

    const load = async () => {
        const [response] = await Promise.all([
            api.get('/user/order/fetch'),
            fetchCommConfig()
        ]);
        return (response && response.data) || [];
    };

    const render = async () => {
        let orders = [];
        try {
            orders = await load();
        } catch (error) {
            el.replaceChildren(errorBlock(error, () => mountList(el)));
            return;
        }

        el.replaceChildren(h('div', { class: 'stack' },
            card({
                flush: true,
                body: ordersTable(orders, render),
                className: 'orders-card'
            })
        ));
    };

    await render();
}

/* ---------------- order detail ---------------- */

const STRIPE_SCRIPT = 'https://js.stripe.com/v3/';

function loadStripe() {
    if (window.Stripe) return Promise.resolve(window.Stripe);
    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-stripe]');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.Stripe));
            existing.addEventListener('error', () => reject(new Error('Stripe failed to load')));
            return;
        }
        const script = document.createElement('script');
        script.src = STRIPE_SCRIPT;
        script.async = true;
        script.dataset.stripe = '1';
        script.onload = () => resolve(window.Stripe);
        script.onerror = () => reject(new Error('Stripe failed to load'));
        document.head.appendChild(script);
    });
}

function paymentFee(method, amount) {
    const fixed = Number(method.handling_fee_fixed || 0);
    const percent = Number(method.handling_fee_percent || 0);
    if (!fixed && !percent) return 0;
    return Math.round((amount * percent) / 100 + fixed);
}

function methodBadge(method) {
    if (method.icon) {
        return h('img', { src: method.icon, alt: method.name });
    }
    return h('span', {
        class: 'logo-mark',
        style: { width: '26px', height: '26px', borderRadius: '6px', fontSize: '12px' }
    }, String(method.name || '?').slice(0, 1));
}

function item(label, value, options = {}) {
    return h('div', { class: 'summary-item' },
        h('div', { class: 'si-label' }, label),
        h('div', { class: `si-value ${options.className || ''}`.trim() }, value)
    );
}

async function payWithStripe(method, order, onDone) {
    let pk = '';
    try {
        const response = await api.post('/user/comm/getStripePublicKey', { id: method.id });
        pk = response ? response.data : '';
    } catch (error) {
        toast(error.message || t('请求失败'), 'error');
        return;
    }
    if (!pk) {
        toast(t('Stripe 未配置'), 'error');
        return;
    }

    const cardBox = h('div', {
        style: {
            padding: '12px',
            border: '1px solid var(--border-strong)',
            borderRadius: '4px',
            marginTop: '12px'
        }
    });
    const submit = button(t('立即支付'), { type: 'primary' });
    const controller = modal({
        title: t('填写信用卡支付信息'),
        body: h('div', {},
            alert('info', '', t('您的信用卡信息只会被用作当次扣款，系统并不会保存，这是我们认为最安全的。')),
            cardBox
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });

    try {
        const Stripe = await loadStripe();
        const stripe = Stripe(pk);
        const elements = stripe.elements();
        const card = elements.create('card', { hidePostalCode: true });
        card.mount(cardBox);
        submit.addEventListener('click', async () => {
            submit.disabled = true;
            try {
                const result = await stripe.createToken(card);
                if (result.error) {
                    toast(result.error.message, 'error');
                    submit.disabled = false;
                    return;
                }
                controller.close();
                await checkout(method, order, result.token.id, onDone);
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
                submit.disabled = false;
            }
        });
    } catch (error) {
        toast(error.message || t('请求失败'), 'error');
        controller.close();
    }
}

function qrPay(method, order, data, onDone) {
    let polling = null;
    const checkStatus = async (manual = false) => {
        try {
            const response = await api.get('/user/order/check', { trade_no: order.trade_no });
            const status = response ? Number(response.data) : 0;
            if (status !== 0) {
                if (polling) window.clearInterval(polling);
                controller.close();
                toast(t('订单支付成功'));
                onDone();
            } else if (manual) {
                toast(t('尚未收到支付结果，请稍后再试'), 'info');
            }
        } catch (error) {
            if (manual) toast(error.message || t('请求失败'), 'error');
        }
    };

    const controller = modal({
        title: t('二维码支付'),
        width: 'narrow',
        body: h('div', {},
            h('p', { class: 'muted' }, t('请使用手机扫描二维码完成支付')),
            qrImage(data)
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('关闭')),
            button(t('我已完成支付'), { type: 'primary', onClick: () => checkStatus(true) })
        ],
        onClose: () => {
            if (polling) window.clearInterval(polling);
        }
    });
    polling = window.setInterval(() => checkStatus(false), 5000);
}

async function checkout(method, order, stripeToken, onDone) {
    const response = await api.post('/user/order/checkout', {
        trade_no: order.trade_no,
        method: method.id,
        token: stripeToken || undefined
    });
    const type = response ? Number(response.type) : 0;
    const data = response ? response.data : null;

    if (type === -1) {
        toast(t('订单支付成功'));
        onDone();
        return;
    }
    if (type === 1) {
        toast(t('正在跳转到支付页面...'), 'info');
        window.location.href = data;
        return;
    }
    if (type === 0) {
        qrPay(method, order, data, onDone);
        return;
    }
    if (type === 2) {
        if (data) {
            toast(t('订单支付成功'));
            onDone();
        } else {
            toast(t('支付失败，请重试'), 'error');
        }
    }
}

export async function mountDetail(el, ctx) {
    el.replaceChildren(loadingBlock());
    const tradeNo = ctx.params.tradeNo;

    const load = async () => {
        const [detailResponse, methodsResponse] = await Promise.allSettled([
            api.get('/user/order/detail', { trade_no: tradeNo }),
            api.get('/user/order/getPaymentMethod'),
            fetchCommConfig()
        ]);
        if (detailResponse.status === 'rejected') throw detailResponse.reason;
        return {
            order: detailResponse.value ? detailResponse.value.data : null,
            methods: methodsResponse.status === 'fulfilled' && methodsResponse.value ? (methodsResponse.value.data || []) : []
        };
    };

    const render = async () => {
        let order = null;
        let methods = [];
        try {
            const result = await load();
            order = result.order;
            methods = result.methods;
        } catch (error) {
            el.replaceChildren(errorBlock(error, () => mountDetail(el, ctx)));
            return;
        }
        if (!order) {
            el.replaceChildren(errorBlock(new Error(t('订单不存在')), () => mountDetail(el, ctx)));
            return;
        }

        const status = Number(order.status);
        const methodState = { selected: methods.length ? methods[0] : null, stripeToken: null };

        const summary = h('div', { class: 'order-summary' });
        const plan = order.plan || {};
        summary.appendChild(item(t('商品信息'), `${plan.name || '-'} · ${periodLabel(order.period)}`));
        summary.appendChild(item(t('订单金额'), money(order.total_amount), { className: 'price' }));
        if (order.discount_amount) summary.appendChild(item(t('优惠金额'), `- ${money(order.discount_amount)}`));
        if (order.balance_amount) summary.appendChild(item(t('余额支付'), `- ${money(order.balance_amount)}`));
        if (order.handling_amount) summary.appendChild(item(t('支付手续费'), `+ ${money(order.handling_amount)}`));
        summary.appendChild(item(t('订单状态'), statusTag(status)));
        summary.appendChild(item(t('创建时间'), formatDate(order.created_at, true)));
        summary.appendChild(item(t('订单号'), h('span', { class: 'mono' }, order.trade_no)));
        if (plan.transfer_enable) summary.appendChild(item(t('总流量'), formatBytes(plan.transfer_enable)));

        const nodes = [
            h('div', { class: 'flex-between' },
                h('div', {},
                    h('h1', { class: 'page-title' }, t('订单详情')),
                    h('div', { class: 'page-desc' }, t('查看订单状态并完成支付'))
                ),
                h('div', { class: 'btn-group' },
                    button(t('返回列表'), { iconName: 'arrow-left', onClick: () => navigate('/order') }),
                    button(t('刷新'), { iconName: 'refresh', onClick: () => render() })
                )
            ),
            card({ title: t('订单信息'), body: summary })
        ];

        if (status === 3) {
            nodes.push(alert('success', t('已完成'), t('订单已支付并开通。'),
                button(t('查看节点状态'), { size: 'sm', type: 'primary', onClick: () => navigate('/node') })));
        } else if (status === 1) {
            nodes.push(alert('info', t('开通中'), t('订单系统正在进行处理，请稍等1-3分钟。')));
        } else if (status === 2 || status === 4) {
            nodes.push(alert('warning', t('已取消'), t('订单由于超时支付已被取消。')));
        }

        if (status === 0) {
            const feeBox = h('div', { class: 'field-hint' });
            const totalBox = h('div', { class: 'plan-name', style: { margin: '8px 0 0', fontSize: '22px' } }, money(order.total_amount));
            const payButton = button(t('立即支付'), {
                type: 'primary',
                size: 'lg',
                onClick: async () => {
                    if (!methodState.selected) {
                        toast(t('请选择支付方式'), 'error');
                        return;
                    }
                    payButton.disabled = true;
                    try {
                        if (methodState.selected.payment === 'StripeCredit') {
                            payWithStripe(methodState.selected, order, () => render());
                        } else {
                            await checkout(methodState.selected, order, null, () => render());
                        }
                    } catch (error) {
                        toast(error.message || t('请求失败'), 'error');
                    } finally {
                        payButton.disabled = false;
                    }
                }
            });

            const payList = h('div', { class: 'pay-list' });
            const refreshTotal = () => {
                const method = methodState.selected;
                const fee = method ? paymentFee(method, Number(order.total_amount || 0)) : 0;
                totalBox.textContent = money(Number(order.total_amount || 0) + fee);
                feeBox.textContent = fee > 0 ? `${t('支付手续费')}: + ${money(fee)}` : '';
            };

            methods.forEach(method => {
                const node = h('div', {
                    class: 'pay-item',
                    role: 'button',
                    tabindex: '0',
                    onClick: () => {
                        methodState.selected = method;
                        Array.from(payList.children).forEach(child => child.classList.remove('selected'));
                        node.classList.add('selected');
                        refreshTotal();
                    }
                },
                    methodBadge(method),
                    h('div', {},
                        h('div', { class: 'pay-name' }, method.name),
                        paymentFee(method, Number(order.total_amount || 0)) > 0
                            ? h('div', { class: 'pay-fee' }, `+ ${money(paymentFee(method, Number(order.total_amount || 0)))}`)
                            : null
                    )
                );
                if (methodState.selected && method.id === methodState.selected.id) node.classList.add('selected');
                payList.appendChild(node);
            });

            refreshTotal();

            const payBody = h('div', {},
                methods.length
                    ? h('div', {},
                        h('div', { class: 'field-label' }, t('支付方式')),
                        payList
                    )
                    : alert('warning', '', t('暂无可用支付方式')),
                h('div', { class: 'divider' }),
                h('div', { class: 'flex-between' },
                    h('div', {},
                        h('div', { class: 'muted' }, t('应付金额')),
                        feeBox,
                        totalBox
                    ),
                    payButton
                )
            );

            nodes.push(card({
                title: t('选择支付方式'),
                extra: button(t('关闭订单'), {
                    size: 'sm',
                    type: 'danger',
                    onClick: async () => {
                        const ok = await confirmDialog({
                            title: t('关闭订单'),
                            content: t('如果你已经付款，取消订单可能会导致支付失败，确定取消订单吗？'),
                            okText: t('确定取消'),
                            danger: true
                        });
                        if (!ok) return;
                        try {
                            await api.post('/user/order/cancel', { trade_no: order.trade_no });
                            toast(t('订单已取消'));
                            render();
                        } catch (error) {
                            toast(error.message || t('请求失败'), 'error');
                        }
                    }
                }),
                body: payBody
            }));
        }

        el.replaceChildren(h('div', { class: 'stack' }, nodes));
    };

    await render();
}
