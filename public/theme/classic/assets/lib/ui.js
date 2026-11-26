import { h, icon, copyText } from './dom.js';
import { t } from './i18n.js';

/* ---------------- toast ---------------- */

let toastStack = null;

function ensureToastStack() {
    if (!toastStack) {
        toastStack = h('div', { class: 'toast-stack' });
        document.body.appendChild(toastStack);
    }
    return toastStack;
}

export function toast(message, type = 'success', duration = 2200) {
    const stack = ensureToastStack();
    const iconName = type === 'error' ? 'alert-circle' : type === 'info' ? 'info' : 'check';
    const node = h('div', { class: `toast ${type}` },
        icon(iconName, 16),
        h('span', {}, message)
    );
    stack.appendChild(node);
    window.setTimeout(() => {
        node.classList.add('leaving');
        window.setTimeout(() => node.remove(), 200);
    }, duration);
    return node;
}

/* ---------------- modal ---------------- */

let openModals = 0;

function lockScroll() {
    openModals += 1;
    document.body.style.overflow = 'hidden';
}

function unlockScroll() {
    openModals = Math.max(0, openModals - 1);
    if (!openModals) document.body.style.overflow = '';
}

function toNode(value) {
    if (value === null || value === undefined || value === false) return document.createDocumentFragment();
    if (value instanceof Node) return value;
    if (Array.isArray(value)) {
        const fragment = document.createDocumentFragment();
        value.forEach(item => {
            const node = toNode(item);
            if (node) fragment.appendChild(node);
        });
        return fragment;
    }
    return document.createTextNode(String(value));
}

export function modal(options = {}) {
    const {
        title = '',
        body = null,
        footer = null,
        width = '',
        closable = true,
        onClose = null
    } = options;

    const mask = h('div', { class: 'modal-mask' });
    const panel = h('div', { class: `modal ${width}`.trim(), role: 'dialog', 'aria-modal': 'true' });
    const bodyEl = h('div', { class: 'modal-body' }, toNode(body));
    let closed = false;

    const close = () => {
        if (closed) return;
        closed = true;
        document.removeEventListener('keydown', onKeyDown);
        mask.remove();
        unlockScroll();
        if (typeof onClose === 'function') onClose();
    };

    const onKeyDown = event => {
        if (event.key === 'Escape' && closable) close();
    };

    if (title || closable) {
        const head = h('div', { class: 'modal-head' },
            h('div', { class: 'modal-title' }, title),
            closable ? h('button', {
                class: 'modal-close',
                type: 'button',
                'aria-label': t('关闭'),
                onClick: close
            }, icon('x', 18)) : null
        );
        panel.appendChild(head);
    }

    panel.appendChild(bodyEl);

    if (footer) {
        const foot = h('div', { class: 'modal-foot' });
        const content = toNode(footer);
        if (content instanceof Node) foot.appendChild(content);
        panel.appendChild(foot);
    }

    mask.appendChild(panel);
    mask.addEventListener('mousedown', event => {
        if (event.target === mask && closable) close();
    });
    document.addEventListener('keydown', onKeyDown);
    document.body.appendChild(mask);
    lockScroll();

    return { el: panel, body: bodyEl, close };
}

export function confirmDialog(options = {}) {
    const {
        title = t('确认'),
        content = '',
        okText = t('确认'),
        cancelText = t('取消'),
        danger = false
    } = options;

    return new Promise(resolve => {
        let settled = false;
        const finish = value => {
            if (settled) return;
            settled = true;
            controller.close();
            resolve(value);
        };
        const controller = modal({
            title,
            width: 'narrow',
            body: h('div', { class: 'confirm-text' }, toNode(content)),
            footer: [
                h('button', { class: 'btn', type: 'button', onClick: () => finish(false) }, cancelText),
                h('button', {
                    class: `btn ${danger ? 'btn-danger' : 'btn-primary'}`,
                    type: 'button',
                    onClick: () => finish(true)
                }, okText)
            ],
            onClose: () => {
                if (!settled) {
                    settled = true;
                    resolve(false);
                }
            }
        });
    });
}

/* ---------------- blocks ---------------- */

function spinner() {
    return h('span', { class: 'spinner', 'aria-hidden': 'true' });
}

export function loadingBlock(message) {
    return h('div', { class: 'loading' }, spinner(), h('span', {}, message || t('加载中...')));
}

export function emptyBlock(options = {}) {
    const { title = t('暂无数据'), desc = '', iconName = 'inbox', action = null } = options;
    return h('div', { class: 'empty' },
        h('div', {}, icon(iconName, 40)),
        h('div', { class: 'empty-title' }, title),
        desc ? h('div', { class: 'empty-desc' }, desc) : null,
        action ? h('div', { class: 'mt-12' }, action) : null
    );
}

export function errorBlock(error, retry) {
    const message = error && error.message ? error.message : t('加载失败');
    return h('div', { class: 'empty' },
        h('div', {}, icon('alert-circle', 40)),
        h('div', { class: 'empty-title' }, t('加载失败')),
        h('div', { class: 'empty-desc' }, message),
        retry ? h('div', { class: 'mt-12' }, h('button', { class: 'btn', type: 'button', onClick: retry }, t('重试'))) : null
    );
}

export function card(options = {}) {
    const { title = '', extra = null, body = null, className = '', id = '', flush = false } = options;
    const head = (title || extra) ? h('div', { class: 'card-head' },
        h('h2', { class: 'card-title' }, title),
        extra ? h('div', { class: 'card-extra' }, toNode(extra)) : null
    ) : null;
    return h('section', { class: `card ${className}`.trim(), id },
        head,
        h('div', { class: `card-body ${flush ? 'flush' : ''}`.trim() }, toNode(body))
    );
}

/* ---------------- form controls ---------------- */

export function input(props = {}) {
    const { class: className = '', ...rest } = props;
    return h('input', { class: `input ${className}`.trim(), ...rest });
}

export function textarea(props = {}) {
    const { class: className = '', ...rest } = props;
    return h('textarea', { class: `textarea ${className}`.trim(), ...rest });
}

export function select(options, value, onChange, props = {}) {
    const el = h('select', {
        class: 'select',
        ...props,
        onChange: event => {
            if (typeof onChange === 'function') onChange(event.target.value, event);
        }
    });
    (options || []).forEach(option => {
        const item = typeof option === 'string' ? { label: option, value: option } : option;
        el.appendChild(h('option', { value: item.value, selected: String(item.value) === String(value) }, item.label));
    });
    el.value = value;
    return el;
}

export function field(options = {}) {
    const { label = '', control = null, hint = '', required = false, error = '' } = options;
    return h('div', { class: 'field' },
        label ? h('label', { class: 'field-label' }, label, required ? h('span', { class: 'req' }, '*') : null) : null,
        toNode(control),
        hint ? h('div', { class: 'field-hint' }, hint) : null,
        error ? h('div', { class: 'field-error' }, error) : null
    );
}

export function button(label, options = {}) {
    const {
        type = 'default',
        size = '',
        iconName = '',
        onClick = null,
        disabled = false,
        block = false,
        title = '',
        attrs = {}
    } = options;
    const cls = ['btn'];
    if (type === 'primary') cls.push('btn-primary');
    if (type === 'danger') cls.push('btn-danger');
    if (type === 'ghost') cls.push('btn-ghost');
    if (type === 'link') cls.push('btn-link');
    if (size) cls.push(`btn-${size}`);
    if (block) cls.push('btn-block');
    return h('button', {
        class: cls.join(' '),
        type: 'button',
        title,
        disabled,
        onClick,
        ...attrs
    }, iconName ? icon(iconName, size === 'sm' ? 13 : 15) : null, label ? h('span', {}, label) : null);
}

export function copyButton(value, label = t('复制'), options = {}) {
    return button(label, {
        ...options,
        iconName: options.iconName === undefined ? 'copy' : options.iconName,
        onClick: async () => {
            const content = typeof value === 'function' ? value() : value;
            const ok = await copyText(content);
            toast(ok ? t('已复制到剪贴板') : t('复制失败，请手动复制'), ok ? 'success' : 'error');
            if (options.onCopied) options.onCopied(ok);
        }
    });
}

function switchControl(checked, onChange) {
    const el = h('button', {
        class: `switch ${checked ? 'on' : ''}`.trim(),
        type: 'button',
        role: 'switch',
        'aria-checked': checked ? 'true' : 'false',
        onClick: () => {
            const next = !el.classList.contains('on');
            el.classList.toggle('on', next);
            el.setAttribute('aria-checked', next ? 'true' : 'false');
            if (typeof onChange === 'function') onChange(next);
        }
    });
    return el;
}

export function switchRow(title, desc, checked, onChange) {
    return h('div', { class: 'switch-row' },
        h('div', {},
            h('div', { class: 'switch-text' }, title),
            desc ? h('div', { class: 'switch-desc' }, desc) : null
        ),
        switchControl(checked, onChange)
    );
}

/* ---------------- display ---------------- */

export function tag(label, type = '') {
    return h('span', { class: `tag ${type ? `tag-${type}` : ''}`.trim() }, label);
}

export function alert(type, title, text, action) {
    const iconName = type === 'danger' ? 'alert-circle' : type === 'warning' ? 'alert-triangle' : type === 'success' ? 'check' : 'info';
    return h('div', { class: `alert alert-${type}` },
        icon(iconName, 16),
        h('div', { class: 'alert-body' },
            title ? h('div', { class: 'alert-title' }, title) : null,
            text ? h('div', {}, text) : null
        ),
        action || null
    );
}

export function progress(percent, tone = '') {
    const value = Math.max(0, Math.min(100, Number(percent || 0)));
    return h('div', { class: 'progress' },
        h('div', { class: `progress-bar ${tone}`.trim(), style: { width: `${value}%` } })
    );
}

export function table(options = {}) {
    const { columns = [], rows = [], rowKey = 'id', empty = null } = options;
    const wrap = h('div', { class: 'table-wrap' });
    const tableEl = h('table', { class: 'table' });
    const thead = h('thead', {}, h('tr', {}, columns.map(column =>
        h('th', { class: column.className || '', style: column.width ? { width: column.width } : {} }, column.title)
    )));
    const tbody = h('tbody');

    if (!rows.length) {
        tbody.appendChild(h('tr', {}, h('td', { colspan: columns.length || 1 },
            empty || emptyBlock()
        )));
    } else {
        rows.forEach((row, index) => {
            const tr = h('tr', { dataset: { key: typeof rowKey === 'function' ? rowKey(row) : row[rowKey] } },
                columns.map(column => {
                    const content = typeof column.render === 'function' ? column.render(row, index) : row[column.key];
                    return h('td', { class: column.cellClass || '' }, toNode(content));
                })
            );
            tbody.appendChild(tr);
        });
    }

    tableEl.appendChild(thead);
    tableEl.appendChild(tbody);
    wrap.appendChild(tableEl);
    return wrap;
}

export function pagination(options = {}) {
    const { current = 1, total = 0, pageSize = 10, onChange = () => {} } = options;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    const wrap = h('div', { class: 'pagination' });
    wrap.appendChild(h('span', { class: 'page-info' }, t('共 {total} 条', { total })));

    const addPage = page => {
        wrap.appendChild(h('button', {
            class: `page-btn ${page === current ? 'active' : ''}`.trim(),
            type: 'button',
            onClick: () => {
                if (page !== current) onChange(page);
            }
        }, String(page)));
    };

    wrap.appendChild(h('button', {
        class: 'page-btn',
        type: 'button',
        disabled: current <= 1,
        onClick: () => onChange(current - 1)
    }, t('上一页')));

    const pages = [];
    const push = page => {
        if (page >= 1 && page <= totalPages && !pages.includes(page)) pages.push(page);
    };
    push(1);
    for (let page = current - 2; page <= current + 2; page += 1) push(page);
    push(totalPages);
    pages.sort((a, b) => a - b);

    let previous = 0;
    pages.forEach(page => {
        if (previous && page - previous > 1) {
            wrap.appendChild(h('span', { class: 'faint' }, '...'));
        }
        addPage(page);
        previous = page;
    });

    wrap.appendChild(h('button', {
        class: 'page-btn',
        type: 'button',
        disabled: current >= totalPages,
        onClick: () => onChange(current + 1)
    }, t('下一页')));

    return wrap;
}

export function qrImage(content, options = {}) {
    const wrapper = h('div', { class: 'qr-box' });
    const showContent = options.showContent !== false;
    const fallback = h('div', { class: 'qr-fallback' }, showContent ? content : t('二维码加载失败'));
    const img = h('img', {
        alt: t('订阅二维码'),
        src: `https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=${encodeURIComponent(content)}`,
        onError: () => {
            img.remove();
            wrapper.prepend(fallback);
        }
    });
    wrapper.appendChild(img);
    if (showContent) {
        wrapper.appendChild(h('div', { class: 'small muted break' }, content));
    }
    return wrapper;
}

/* ---------------- labels ---------------- */

const PERIOD_KEYS = {
    month_price: '月付',
    quarter_price: '季付',
    half_year_price: '半年付',
    year_price: '年付',
    two_year_price: '两年付',
    three_year_price: '三年付',
    onetime_price: '一次性',
    reset_price: '流量重置包',
    deposit: '充值'
};

export function periodLabel(period) {
    return t(PERIOD_KEYS[period] || period || '-');
}

const ORDER_STATUS = {
    0: { label: '待支付', type: 'warning' },
    1: { label: '开通中', type: 'primary' },
    2: { label: '已取消', type: 'muted' },
    3: { label: '已完成', type: 'success' },
    4: { label: '已折抵', type: 'muted' }
};

export function statusTag(status) {
    const info = ORDER_STATUS[Number(status)] || { label: '-', type: 'muted' };
    return tag(t(info.label), info.type);
}

export function ticketStatus(ticket) {
    if (Number(ticket.status) === 1) return tag(t('已关闭'), 'muted');
    if (Number(ticket.reply_status) === 1) return tag(t('已回复'), 'success');
    return tag(t('待回复'), 'warning');
}

const LEVELS = { 0: { label: '低', type: 'muted' }, 1: { label: '中', type: 'warning' }, 2: { label: '高', type: 'danger' } };

export function levelTag(level) {
    const info = LEVELS[Number(level)] || LEVELS[0];
    return tag(t(info.label), info.type);
}

export { toNode };
