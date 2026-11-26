import { h, icon } from './lib/dom.js';
import { t } from './lib/i18n.js';
import { fetchUser, cachedUser, logout } from './lib/api.js';
import { navigate } from './lib/router.js';

const MENU = [
    {
        items: [
            { key: 'dashboard', label: '仪表盘', icon: 'si-speedometer', path: '/dashboard' },
            { key: 'knowledge', label: '使用文档', icon: 'si-book-open', path: '/knowledge' }
        ]
    },
    {
        group: '订阅',
        items: [
            { key: 'plan', label: '购买订阅', icon: 'si-bag', path: '/plan' },
            { key: 'node', label: '节点状态', icon: 'si-check', path: '/node' },
            { key: 'dialer', label: '链式代理', icon: 'si-link', path: '/dialer-proxy', condition: settings => Number(settings.dialer_proxy_enable) === 1 }
        ]
    },
    {
        group: '财务',
        items: [
            { key: 'order', label: '我的订单', icon: 'si-list', path: '/order' },
            { key: 'invite', label: '我的邀请', icon: 'si-users', path: '/invite' }
        ]
    },
    {
        group: '用户',
        items: [
            { key: 'profile', label: '个人中心', icon: 'si-user', path: '/profile' },
            { key: 'ticket', label: '我的工单', icon: 'si-support', path: '/ticket' },
            { key: 'traffic', label: '流量明细', icon: 'si-bar-chart', path: '/traffic' }
        ]
    }
];

function brand(settings) {
    const logo = settings.logo
        ? h('img', { src: settings.logo, alt: settings.title || 'logo' })
        : null;
    return h('a', { class: 'logo', href: '#/dashboard' },
        logo,
        h('span', { class: 'logo-text' }, settings.title || 'V2Board')
    );
}

function menuItem(item, active, onNavigate) {
    const iconEl = String(item.icon || '').startsWith('si-')
        ? h('i', { class: `si ${item.icon}`, 'aria-hidden': 'true' })
        : icon(item.icon, 17);
    return h('a', {
        class: `menu-item ${active === item.key ? 'active' : ''}`.trim(),
        href: `#${item.path}`,
        onClick: () => {
            if (typeof onNavigate === 'function') onNavigate();
        }
    }, iconEl, h('span', {}, t(item.label)));
}

function userMenu(user) {
    const email = user && user.email ? user.email : t('个人中心');
    const dropdown = h('div', { class: 'dropdown' });

    const trigger = h('button', {
        class: 'icon-btn user-trigger',
        type: 'button',
        title: email,
        onClick: event => {
            event.stopPropagation();
            dropdown.classList.toggle('open');
        }
    },
        icon('user', 15),
        h('span', { class: 'user-email' }, email),
        icon('chevron-down', 14)
    );

    const menu = h('div', { class: 'dropdown-menu' },
        h('a', { class: 'dropdown-item', href: '#/profile' }, icon('user', 15), h('span', {}, t('个人中心'))),
        h('div', { class: 'dropdown-divider' }),
        h('button', {
            class: 'dropdown-item',
            type: 'button',
            style: { width: '100%', border: 0, background: 'none', textAlign: 'left' },
            onClick: async () => {
                logout();
                navigate('/login');
            }
        }, icon('logout', 15), h('span', {}, t('退出登录')))
    );

    dropdown.appendChild(trigger);
    dropdown.appendChild(menu);

    const close = () => dropdown.classList.remove('open');
    document.addEventListener('click', close);
    window.addEventListener('hashchange', () => {
        document.removeEventListener('click', close);
        close();
    }, { once: true });

    return dropdown;
}

export function renderShell(options = {}) {
    const { active = '', title = '', content = null } = options;
    const settings = window.settings || {};
    const user = cachedUser();

    const sider = h('aside', { class: 'sider' },
        brand(settings),
        h('nav', { class: 'menu' },
            MENU.map(section => h('div', {},
                section.group ? h('div', { class: 'menu-group' }, t(section.group)) : null,
                section.items.filter(item => !item.condition || item.condition(settings)).map(item => menuItem(item, active, () => closeDrawer()))
            ))
        ),
        h('div', { class: 'footer', style: { padding: '12px 18px', textAlign: 'left' } },
            h('div', { class: 'small faint' }, `v${settings.version || ''}`)
        )
    );

    const mask = h('div', { class: 'sider-mask', onClick: () => closeDrawer() });

    const closeDrawer = () => {
        sider.classList.remove('open');
        mask.classList.remove('open');
    };

    const header = h('header', { class: 'header' },
        h('div', { class: 'header-inner' },
            h('button', {
                class: 'icon-btn menu-trigger',
                type: 'button',
                'aria-label': 'menu',
                onClick: () => {
                    sider.classList.toggle('open');
                    mask.classList.toggle('open');
                }
            }, icon('menu', 20)),
            h('div', { class: 'header-title' }, title),
            userMenu(user)
        )
    );

    const contentWrap = h('main', { class: 'content' }, content || '');

    const main = h('div', { class: 'main' }, header, contentWrap);

    document.title = `${title ? `${title} - ` : ''}${settings.title || 'V2Board'}`;

    // Refresh the header email after the user profile is available.
    if (settings.mock || !user) {
        fetchUser().then(info => {
            if (!info) return;
            const current = header.querySelector('.dropdown');
            if (current && current.parentElement) {
                current.parentElement.replaceChild(userMenu(info), current);
            }
        }).catch(() => {});
    }

    return h('div', { class: 'app' }, sider, mask, main);
}

export function authBrand(settings) {
    const logo = settings.logo
        ? h('img', { src: settings.logo, alt: settings.title || 'logo' })
        : h('span', { class: 'logo-mark' }, String(settings.title || 'V').slice(0, 1).toUpperCase());
    return h('div', { class: 'auth-brand' },
        logo,
        h('h1', {}, settings.title || 'V2Board'),
        settings.description ? h('p', {}, settings.description) : null
    );
}
