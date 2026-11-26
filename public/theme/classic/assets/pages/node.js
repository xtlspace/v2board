import { h, nowSeconds } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, fetchSubscribe } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import {
    alert, button, card, emptyBlock, errorBlock, loadingBlock, table, tag
} from '../lib/ui.js';

function statusCell(server) {
    const online = Number(server.is_online) === 1;
    const last = Number(server.last_check_at || 0);
    const minutes = last ? Math.max(0, Math.round((nowSeconds() - last) / 60)) : null;
    return h('div', { class: 'flex-center gap-8' },
        h('span', {
            style: {
                width: '8px',
                height: '8px',
                borderRadius: '50%',
                background: online ? 'var(--success)' : 'var(--danger)',
                display: 'inline-block',
                flex: '0 0 auto'
            }
        }),
        h('span', {}, online ? t('在线') : t('离线')),
        minutes !== null ? h('span', { class: 'small faint' }, `${minutes} ${t('分钟前')}`) : null
    );
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());

    let subscribe = null;
    let servers = [];
    try {
        const [subscribeResponse, serverResponse] = await Promise.all([
            fetchSubscribe(true),
            api.get('/user/server/fetch')
        ]);
        subscribe = subscribeResponse;
        servers = (serverResponse && serverResponse.data) || [];
    } catch (error) {
        el.replaceChildren(errorBlock(error, () => mount(el)));
        return;
    }

    const available = Boolean(subscribe && subscribe.plan_id && subscribe.expired_at && Number(subscribe.expired_at) > nowSeconds());
    const state = { keyword: '' };

    const searchInput = h('input', {
        class: 'input',
        type: 'search',
        placeholder: t('搜索'),
        style: { width: '200px' },
        onInput: () => {
            state.keyword = searchInput.value.trim().toLowerCase();
            renderTable();
        }
    });

    const tableBox = h('div', {});

    const renderTable = () => {
        const rows = servers.filter(server => {
            if (!state.keyword) return true;
            return String(server.name || '').toLowerCase().includes(state.keyword)
                || String(server.tags || '').toLowerCase().includes(state.keyword);
        });

        tableBox.replaceChildren(table({
            columns: [
                {
                    title: t('节点名称'),
                    render: server => h('div', {},
                        h('div', {}, server.name),
                        server.tags ? h('div', { class: 'tag-list mt-8' },
                            String(server.tags).split(',').filter(Boolean).map(name => tag(name, ''))
                        ) : null
                    )
                },
                { title: t('倍率'), width: '90px', render: server => h('span', {}, `${server.rate || '1'}x`) },
                { title: t('状态'), width: '200px', render: server => statusCell(server) }
            ],
            rows,
            rowKey: 'id',
            empty: emptyBlock({
                iconName: 'server',
                title: t('暂无节点'),
                desc: available ? t('请稍后再试') : t('您的订阅已到期或流量已用尽'),
                action: available ? button(t('刷新'), { onClick: () => mount(el) }) : button(t('购买订阅'), {
                    type: 'primary',
                    onClick: () => navigate('/plan')
                })
            })
        }));
    };

    renderTable();

    const nodes = [
        h('div', { class: 'flex-between flex-wrap' },
            h('div', {},
                h('h1', { class: 'page-title' }, t('节点状态')),
                h('div', { class: 'page-desc' }, t('节点五分钟内节点在线情况'))
            ),
            Number(window.settings && window.settings.dialer_proxy_enable) === 1
                ? h('div', { class: 'btn-group' },
                    button(t('链式代理'), {
                        type: 'primary',
                        iconName: 'wifi',
                        onClick: () => navigate('/dialer-proxy')
                    })
                )
                : null
        )
    ];

    if (!available) {
        nodes.push(alert('warning', t('已过期'), t('您的订阅已到期或流量已用尽'),
            button(t('购买订阅'), { size: 'sm', type: 'primary', onClick: () => navigate('/plan') })));
    }

    nodes.push(card({
        title: t('节点在线情况'),
        extra: h('div', { class: 'card-extra' }, searchInput),
        flush: true,
        body: tableBox
    }));

    el.replaceChildren(h('div', { class: 'stack' }, nodes));
}
