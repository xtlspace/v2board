import { h, raw, money, formatBytes, formatLongDate, icon } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, fetchUser, fetchSubscribe, fetchCommConfig } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import {
    alert, button, emptyBlock, input, loadingBlock, modal,
    periodLabel, tag, toast, errorBlock
} from '../lib/ui.js';

const PERIODS = [
    'month_price',
    'quarter_price',
    'half_year_price',
    'year_price',
    'two_year_price',
    'three_year_price',
    'onetime_price',
    'reset_price'
];

const MONTH_WEIGHT = {
    month_price: 1,
    quarter_price: 3,
    half_year_price: 6,
    year_price: 12,
    two_year_price: 24,
    three_year_price: 36
};

function availablePeriods(plan, currentPlanId) {
    return PERIODS.filter(period => {
        if (period === 'reset_price') {
            return plan.reset_price !== null && plan.reset_price !== undefined && Number(plan.id) === Number(currentPlanId);
        }
        return plan[period] !== null && plan[period] !== undefined && Number(plan[period]) > 0;
    });
}

function displayPrice(plan) {
    const periods = availablePeriods(plan, null).filter(period => period !== 'reset_price');
    if (!periods.length) return null;
    let best = null;
    periods.forEach(period => {
        const price = Number(plan[period]);
        const weight = MONTH_WEIGHT[period] || 1;
        const monthly = price / weight;
        if (!best || monthly < best.monthly) {
            best = { period, price, monthly };
        }
    });
    return best;
}

function priceText(plan) {
    const best = displayPrice(plan);
    if (!best) return { amount: '-', unit: '' };
    if (best.period === 'onetime_price') {
        return { amount: money(best.price), unit: t('一次性') };
    }
    return { amount: money(Math.round(best.monthly)), unit: `${t('起')} / ${t('月')}` };
}

function specTags(plan) {
    const tags = [];
    if (plan.transfer_enable) {
        tags.push(tag(t('总流量') + ' ' + formatBytes(plan.transfer_enable), 'primary'));
    }
    if (plan.device_limit !== null && plan.device_limit !== undefined) {
        tags.push(tag(t('设备') + ' ' + plan.device_limit, ''));
    } else {
        tags.push(tag(t('设备') + ' ' + t('不限'), ''));
    }
    if (plan.speed_limit) {
        tags.push(tag(plan.speed_limit + ' Mbps', ''));
    }
    if (plan.capacity_limit !== null && plan.capacity_limit !== undefined && Number(plan.capacity_limit) <= 0) {
        tags.push(tag(t('售罄'), 'danger'));
    }
    return h('div', { class: 'tag-list' }, tags);
}

function planCard(plan, currentPlanId, onSubscribe) {
    const price = priceText(plan);
    const isCurrent = Number(plan.id) === Number(currentPlanId);
    const soldOut = plan.capacity_limit !== null && plan.capacity_limit !== undefined && Number(plan.capacity_limit) <= 0;
    return h('section', { class: `card plan-card ${isCurrent ? 'current' : ''}`.trim() },
        h('div', { class: 'plan-head' },
            h('div', { class: 'plan-title' },
                plan.name,
                isCurrent ? tag(t('当前套餐'), 'primary') : null,
                soldOut ? tag(t('售罄'), 'danger') : null
            ),
            h('div', { class: 'plan-price' },
                h('span', { class: 'amount' }, price.amount),
                h('span', { class: 'unit' }, price.unit)
            ),
            specTags(plan)
        ),
        h('div', { class: 'plan-content' }, raw(plan.content || '')),
        h('div', { class: 'plan-actions' },
            button(isCurrent ? t('续费') : t('立即订阅'), {
                type: 'primary',
                block: true,
                disabled: soldOut,
                onClick: () => onSubscribe(plan)
            })
        )
    );
}

function couponDiscount(coupon, base) {
    if (!coupon) return 0;
    if (Number(coupon.type) === 1) return Math.min(base, Number(coupon.value || 0));
    if (Number(coupon.type) === 2) return Math.round(base * (Number(coupon.value || 0) / 100));
    return 0;
}

function openSubscribe(plan, options) {
    const currentPlanId = options.currentPlanId;
    const periods = availablePeriods(plan, currentPlanId);
    if (!periods.length) {
        toast(t('该订阅无法续费，仅允许新用户购买'), 'error');
        return;
    }

    let selectedPeriod = periods[0];
    let coupon = null;

    const summary = h('div', { class: 'mt-16' });
    const couponInput = input({ placeholder: t('有优惠券？'), autocomplete: 'off' });
    const couponResult = h('div', { class: 'field-hint' });
    const couponButton = button(t('验证'), {
        size: 'sm',
        onClick: async () => {
            const code = couponInput.value.trim();
            if (!code) {
                toast(t('请输入优惠券'), 'error');
                return;
            }
            couponButton.disabled = true;
            try {
                const response = await api.post('/user/coupon/check', { code, plan_id: plan.id });
                coupon = response ? response.data : null;
                renderSummary();
                couponResult.textContent = coupon && Number(coupon.type) === 1
                    ? `${t('优惠')} ${money(coupon.value)}`
                    : `${t('优惠')} ${coupon ? coupon.value : 0}%`;
                toast(t('验证成功'));
            } catch (error) {
                coupon = null;
                couponResult.textContent = '';
                renderSummary();
                toast(error.message || t('优惠券无效'), 'error');
            } finally {
                couponButton.disabled = false;
            }
        }
    });

    const basePrice = () => Number(plan[selectedPeriod] || 0);
    const totalPrice = () => Math.max(0, basePrice() - couponDiscount(coupon, basePrice()));

    const renderSummary = () => {
        const base = basePrice();
        const discount = couponDiscount(coupon, base);
        summary.replaceChildren(h('div', {},
            h('div', { class: 'flex-between' }, h('span', { class: 'muted' }, t('商品信息')),
                h('span', {}, `${plan.name} · ${periodLabel(selectedPeriod)}`)),
            h('div', { class: 'flex-between mt-8' }, h('span', { class: 'muted' }, t('原价')), h('span', {}, money(base))),
            discount > 0 ? h('div', { class: 'flex-between mt-8' }, h('span', { class: 'muted' }, t('优惠')), h('span', { class: 'tag tag-success' }, `- ${money(discount)}`)) : null,
            h('div', { class: 'divider' }),
            h('div', { class: 'flex-between' },
                h('span', { class: 'strong' }, t('应付金额')),
                h('span', { class: 'plan-name', style: { margin: 0, fontSize: '22px' } }, money(totalPrice()))
            ),
            Number(options.balance) > 0 ? h('div', { class: 'field-hint mt-8' }, `${t('账户余额(仅消费)')}: ${money(options.balance)}`) : null
        ));
    };

    const periodList = h('div', { class: 'radio-list' });
    periods.forEach(period => {
        const price = Number(plan[period] || 0);
        const item = h('div', {
            class: `radio-card ${period === selectedPeriod ? 'selected' : ''}`.trim(),
            role: 'button',
            tabindex: '0',
            onClick: () => {
                selectedPeriod = period;
                Array.from(periodList.children).forEach(child => child.classList.remove('selected'));
                item.classList.add('selected');
                renderSummary();
            }
        },
            h('div', {},
                h('div', { class: 'rc-name' }, periodLabel(period)),
                h('div', { class: 'rc-desc' }, period === 'reset_price'
                    ? t('购买后仅重置本周期使用流量，不会延长到期时间')
                    : `${formatBytes(plan.transfer_enable || 0)} · ${plan.device_limit || '∞'} ${t('设备')}`)
            ),
            h('div', { class: 'rc-price' }, money(price))
        );
        periodList.appendChild(item);
    });

    renderSummary();

    const body = h('div', {},
        h('div', { class: 'mb-16' }, raw(plan.content || '')),
        h('div', { class: 'field-label' }, t('选择周期')),
        periodList,
        periods.includes('reset_price') ? null : h('div', { class: 'mt-16' },
            h('div', { class: 'field-label' }, t('有优惠券？')),
            h('div', { class: 'input-group' }, couponInput, couponButton),
            couponResult
        ),
        summary,
        h('div', { class: 'field-hint mt-8' }, t('最终金额以订单为准，余额与优惠将自动抵扣。')),
        currentPlanId && Number(currentPlanId) !== Number(plan.id)
            ? h('div', { class: 'mt-16' }, alert('warning', '', t('变更订阅会导致当前订阅被新订阅覆盖，请注意。')))
            : null
    );

    const submit = button(t('下单'), {
        type: 'primary',
        onClick: async () => {
            submit.disabled = true;
            const original = submit.innerHTML;
            submit.textContent = t('提交中...');
            try {
                const response = await api.post('/user/order/save', {
                    plan_id: plan.id,
                    period: selectedPeriod,
                    coupon_code: coupon ? couponInput.value.trim() : undefined
                });
                const tradeNo = response ? response.data : '';
                toast(t('订单已创建'));
                controller.close();
                if (tradeNo) navigate(`/order/${tradeNo}`);
                else navigate('/order');
            } catch (error) {
                toast(error.message || t('请求失败'), 'error', 3200);
                submit.disabled = false;
                submit.innerHTML = original;
            }
        }
    });

    const controller = modal({
        title: t('配置订阅'),
        width: '',
        body,
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());

    const [planResult, userResult, subscribeResult] = await Promise.allSettled([
        api.get('/user/plan/fetch'),
        fetchUser(),
        fetchSubscribe(true),
        fetchCommConfig()
    ]);

    if (planResult.status === 'rejected') {
        el.replaceChildren(errorBlock(planResult.reason, () => mount(el)));
        return;
    }

    const plans = (planResult.value && planResult.value.data) || [];
    const user = userResult.status === 'fulfilled' ? userResult.value : null;
    const subscribe = subscribeResult.status === 'fulfilled' ? subscribeResult.value : null;
    const currentPlanId = subscribe && subscribe.plan_id ? subscribe.plan_id : null;

    const state = { filter: 'all' };
    const grid = h('div', { class: 'plan-grid' });
    const tabsBar = h('div', { class: 'mb-16' });

    const render = () => {
        const filtered = plans.filter(plan => {
            if (state.filter === 'period') return availablePeriods(plan, currentPlanId).some(period => MONTH_WEIGHT[period]);
            if (state.filter === 'onetime') return availablePeriods(plan, currentPlanId).includes('onetime_price');
            return true;
        });

        grid.replaceChildren();
        if (!filtered.length) {
            grid.appendChild(emptyBlock({ title: t('暂无数据'), desc: t('请稍后再试') }));
        } else {
            filtered.forEach(plan => grid.appendChild(planCard(plan, currentPlanId, selected => {
                openSubscribe(selected, {
                    currentPlanId,
                    balance: user ? user.balance : 0
                });
            })));
        }
    };

    const tabItems = [
        { value: 'all', label: t('全部') },
        { value: 'period', label: t('按周期') },
        { value: 'onetime', label: t('按流量') }
    ];

    const tabsEl = h('div', { class: 'tabs' });
    tabItems.forEach(item => {
        tabsEl.appendChild(h('button', {
            class: `tab ${state.filter === item.value ? 'active' : ''}`.trim(),
            type: 'button',
            onClick: () => {
                state.filter = item.value;
                Array.from(tabsEl.children).forEach(child => child.classList.remove('active'));
                const index = tabItems.findIndex(entry => entry.value === item.value);
                if (tabsEl.children[index]) tabsEl.children[index].classList.add('active');
                render();
            }
        }, item.label));
    });

    tabsBar.appendChild(tabsEl);
    render();

    el.replaceChildren(h('div', { class: 'stack' },
        h('div', {},
            h('div', { class: 'flex-between flex-wrap' },
                h('div', {},
                    h('h1', { class: 'page-title' }, t('选择最适合你的计划'))
                ),
                h('div', { class: 'balance-chip' },
                    icon('wallet', 16),
                    h('span', { class: 'bc-label' }, t('当前余额')),
                    h('span', { class: 'bc-value' }, money(user ? user.balance : 0))
                )
            ),
            h('div', { class: 'mt-16' }, tabsBar)
        ),
        currentPlanId && subscribe && subscribe.plan
            ? (() => {
                const remainingTraffic = Math.max(0,
                    Number(subscribe.transfer_enable || 0)
                    - Number(subscribe.u || 0)
                    - Number(subscribe.d || 0)
                );
                const isOnetime = Number(subscribe.expired_at || 0) === 0
                    || (subscribe.plan.onetime_price !== null
                        && subscribe.plan.onetime_price !== undefined
                        && Number(subscribe.plan.onetime_price) > 0);

                let text = `${t('当前套餐')}：${subscribe.plan.name} · ${t('剩余流量')} ${formatBytes(remainingTraffic)}`;
                const expireSeconds = Number(subscribe.expired_at || 0) - Date.now() / 1000;
                if (!isOnetime) {
                    text += expireSeconds <= 0
                        ? ` · ${t('于{date}过期', { date: formatLongDate(subscribe.expired_at) })}`
                        : ` · ${t('剩余 {days} 天', {
                            days: Math.max(0, Math.ceil(expireSeconds / 86400))
                        })}`;
                }
                if (Number(subscribe.surplus_enable) === 1) {
                    text += ` · ${t('可折抵金额')} ${money(subscribe.surplus_amount || 0)}`;
                }
                return alert('info', '', text);
            })()
            : null,
        grid
    ));
}
