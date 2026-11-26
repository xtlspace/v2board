import { h } from './lib/dom.js';
import { t } from './lib/i18n.js';
import { isLoggedIn, fetchUser, fetchCommConfig } from './lib/api.js';
import { defineRoute, resolve, startRouter, currentLocation, navigate } from './lib/router.js';
import { renderShell } from './layout.js';
import { button, card, errorBlock, loadingBlock } from './lib/ui.js';

import * as login from './pages/login.js';
import * as register from './pages/register.js';
import * as forget from './pages/forget.js';
import * as dashboard from './pages/dashboard.js';
import * as plan from './pages/plan.js';
import * as order from './pages/order.js';
import * as node from './pages/node.js';
import * as dialer from './pages/dialer.js';
import * as ticket from './pages/ticket.js';
import * as knowledge from './pages/knowledge.js';
import * as traffic from './pages/traffic.js';
import * as invite from './pages/invite.js';
import * as profile from './pages/profile.js';

defineRoute({ path: '/login', guest: true, title: '登入', page: login });
defineRoute({ path: '/register', guest: true, title: '注册', page: register });
defineRoute({ path: '/forgetpassword', guest: true, title: '忘记密码', page: forget });

defineRoute({ path: '/dashboard', auth: true, active: 'dashboard', title: '仪表盘', page: dashboard });
defineRoute({ path: '/plan', auth: true, active: 'plan', title: '购买订阅', page: plan });
defineRoute({ path: '/order', auth: true, active: 'order', title: '我的订单', page: order, mountKey: 'mountList' });
defineRoute({ path: '/order/:tradeNo', auth: true, active: 'order', title: '订单详情', page: order, mountKey: 'mountDetail' });
defineRoute({ path: '/node', auth: true, active: 'node', title: '节点状态', page: node });
defineRoute({ path: '/dialer', auth: true, active: 'dialer', title: '链式代理', page: dialer });
defineRoute({ path: '/dialer-proxy', auth: true, active: 'dialer', title: '链式代理', page: dialer });
defineRoute({ path: '/ticket', auth: true, active: 'ticket', title: '我的工单', page: ticket });
defineRoute({ path: '/ticket/:id', auth: true, active: 'ticket', title: '工单详情', page: ticket, mountKey: 'mountDetail' });
defineRoute({ path: '/knowledge', auth: true, active: 'knowledge', title: '使用文档', page: knowledge });
defineRoute({ path: '/knowledge/:id', auth: true, active: 'knowledge', title: '使用文档', page: knowledge, mountKey: 'mountDetail' });
defineRoute({ path: '/traffic', auth: true, active: 'traffic', title: '流量明细', page: traffic });
defineRoute({ path: '/invite', auth: true, active: 'invite', title: '我的邀请', page: invite });
defineRoute({ path: '/profile', auth: true, active: 'profile', title: '个人中心', page: profile });

function notFound() {
    const content = h('div', { class: 'stack' },
        card({
            title: '404',
            body: h('div', { class: 'empty' },
                h('div', { class: 'empty-title' }, t('页面不存在')),
                h('div', { class: 'empty-desc' }, t('请检查地址是否正确')),
                h('div', { class: 'mt-16' },
                    button(t('返回仪表盘'), { type: 'primary', onClick: () => navigate('/dashboard') })
                )
            )
        })
    );
    const root = document.getElementById('root');
    root.replaceChildren(renderShell({ active: '', title: '404', content }));
}

function mountPage(route, params, query, container) {
    const mount = route.mountKey && typeof route.page[route.mountKey] === 'function'
        ? route.page[route.mountKey]
        : route.page.mount;
    return Promise.resolve(mount(container, { params, query, route }));
}

function render() {
    const location = currentLocation();
    const path = location.path;

    if (path === '/' || path === '') {
        navigate('/dashboard', { replace: true });
        return;
    }

    const matched = resolve(path);
    if (!matched) {
        notFound();
        window.scrollTo(0, 0);
        return;
    }

    const { route, params } = matched;
    const query = location.query;

    if (route.auth && !isLoggedIn()) {
        const redirect = encodeURIComponent(`${path}${location.raw.includes('?') ? `?${location.raw.split('?').slice(1).join('?')}` : ''}`);
        navigate(`/login?redirect=${redirect}`, { replace: true });
        return;
    }

    if (route.guest && isLoggedIn()) {
        const verifying = path === '/login' && query.verify;
        if (!verifying) {
            navigate('/dashboard', { replace: true });
            return;
        }
    }

    const root = document.getElementById('root');
    if (!root) return;

    if (route.guest) {
        document.title = `${t(route.title)} - ${(window.settings && window.settings.title) || 'V2Board'}`;
        const container = h('div', {});
        root.replaceChildren(container);
        mountPage(route, params, query, container).catch(error => {
            container.replaceChildren(errorBlock(error, () => render()));
        });
        window.scrollTo(0, 0);
        return;
    }

    const content = h('div', { class: 'stack' }, loadingBlock());
    const shell = renderShell({
        active: route.active || '',
        title: t(route.title),
        content
    });
    root.replaceChildren(shell);
    mountPage(route, params, query, content).catch(error => {
        content.replaceChildren(errorBlock(error, () => render()));
    });
    window.scrollTo(0, 0);
}

// Warm the profile cache so the header renders immediately on protected pages.
if (isLoggedIn()) {
    fetchUser().catch(() => {});
    fetchCommConfig().catch(() => {});
}

window.addEventListener('unhandledrejection', event => {
    const reason = event.reason;
    if (reason && reason.message) {
        console.error('[classic-theme]', reason);
    }
});

startRouter(render);
