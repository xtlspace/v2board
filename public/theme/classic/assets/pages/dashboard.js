import { h, icon, formatBytes, formatDate, formatLongDate, daysBetween, hoursBetween, nowSeconds, raw, base64Url } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, fetchSubscribe, subscribeUrlFrom, clearCaches } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import {
    alert, button, confirmDialog, copyButton, emptyBlock, errorBlock, loadingBlock,
    modal, progress, qrImage, toast
} from '../lib/ui.js';

function expireLine(subscribe) {
    if (!subscribe || !subscribe.expired_at) return t('该订阅长期有效');
    if (Number(subscribe.expired_at) <= nowSeconds()) {
        return t('于{date}过期', { date: formatLongDate(subscribe.expired_at) });
    }
    const days = daysBetween(subscribe.expired_at);
    if (days >= 1) {
        return t('于 {date} 到期，距离到期还有 {day} 天。', {
            date: formatDate(subscribe.expired_at),
            day: days
        });
    }
    return t('于 {date} 到期，距离到期还有 {hour} 小时。', {
        date: formatDate(subscribe.expired_at, true),
        hour: Math.max(1, hoursBetween(subscribe.expired_at))
    });
}

function resetLine(subscribe) {
    if (!subscribe || subscribe.reset_day === null || subscribe.reset_day === undefined) return '';
    const days = Number(subscribe.reset_day);
    if (days === 0) {
        if (subscribe.reset_hour) {
            return t('已用流量将在 {reset_hour} 小时后重置', { reset_hour: subscribe.reset_hour });
        }
        return t('已用流量已在今日重置');
    }
    return t('已用流量将在 {reset_day} 日后重置', { reset_day: days });
}

function trafficPercent(subscribe) {
    if (!subscribe || !subscribe.transfer_enable) return 0;
    const used = Number(subscribe.u || 0) + Number(subscribe.d || 0);
    return Math.max(0, Math.min(100, (used / Number(subscribe.transfer_enable)) * 100));
}

function isAppleClientPlatform() {
    const ua = navigator.userAgent || '';
    const isIPadOS = /Macintosh/i.test(ua) && Number(navigator.maxTouchPoints) > 1;
    const isIOS = /iPhone|iPad|iPod/i.test(ua) || isIPadOS;
    const isMac = /Macintosh|Mac OS X/i.test(ua) && !isIPadOS;
    return isIOS || isMac;
}

function siteName() {
    const settings = window.settings || {};
    return settings.site_name || settings.title || 'V2Board';
}

function shadowrocketImportUrl(url) {
    return `shadowrocket://add/sub://${base64Url(url)}?remark=${encodeURIComponent(siteName())}&user=ILoveU-Meta/Rocket`;
}

function clientIcons(url) {
    const settings = window.settings || {};
    const title = siteName();
    const apps = [
        {
            name: 'Clash',
            file: 'Clash.png',
            href: `clash://install-config?url=${encodeURIComponent(url)}&name=${encodeURIComponent(title)}`
        }
    ];
    if (isAppleClientPlatform()) {
        apps.push({
            name: 'Shadowrocket',
            file: 'Shadowrocket.png',
            href: shadowrocketImportUrl(url)
        });
    }
    const assets = settings.assets_path || '/theme/classic/assets';
    const rename = {
        Clash: 'Clash',
        Shadowrocket: 'Shadowrocket'
    };
    return h('div', { class: 'client-icons' },
        h('span', { class: 'small faint', style: { alignSelf: 'center' } }, t('导入到')),
        apps.map(app => h('a', {
            class: 'client-icon primary',
            href: app.href,
            title: `${app.name}`,
            rel: 'noopener noreferrer'
        },
            h('img', { src: `${assets}/images/icon/${app.file}`, alt: app.name }),
            h('span', {}, rename[app.name] || app.name)
        ))
    );
}

function subscriptionCard(subscribe, options) {
    const url = subscribeUrlFrom(subscribe);
    const used = Number(subscribe && subscribe.u ? subscribe.u : 0) + Number(subscribe && subscribe.d ? subscribe.d : 0);
    const total = Number(subscribe && subscribe.transfer_enable ? subscribe.transfer_enable : 0);
    const percent = trafficPercent(subscribe);
    const tone = percent >= 99 ? 'danger' : percent >= 80 ? 'warning' : '';
    const hasPlan = Boolean(subscribe && subscribe.plan_id && subscribe.plan);
    const expired = Boolean(subscribe && subscribe.expired_at && Number(subscribe.expired_at) <= nowSeconds());

    const resetLink = hasPlan ? h('button', {
        class: 'reset-link',
        type: 'button',
        onClick: event => {
            event.preventDefault();
            options.onReset();
        }
    }, t('（'), h('span', { class: 'reset-text' }, t('重置订阅')), t('）')) : null;

    const head = h('div', { class: 'card-head' },
        h('h2', { class: 'card-title' }, t('我的订阅'), resetLink),
        hasPlan ? h('div', { class: 'card-extra' },
            h('a', { class: 'btn btn-sm', href: '#/node' }, icon('server', 13), h('span', {}, t('查看节点状态')))
        ) : null
    );

    const body = h('div', { class: 'card-body' });

    if (!hasPlan) {
        body.appendChild(emptyBlock({
            iconName: 'gift',
            title: subscribe && subscribe.expired_at && Number(subscribe.expired_at) < nowSeconds()
                ? t('于{date}过期', { date: formatLongDate(subscribe.expired_at) })
                : t('您还没有订阅'),
            desc: t('选择最适合你的计划'),
            action: button(t('购买订阅'), { type: 'primary', onClick: () => navigate('/plan') })
        }));
        return h('section', { class: 'card' }, head, body);
    }

    body.appendChild(h('h3', { class: 'plan-name' }, subscribe.plan.name));
    body.appendChild(h('p', {
        class: 'plan-meta',
        style: expired ? { color: 'var(--danger)' } : {}
    }, expireLine(subscribe)));
    const reset = resetLine(subscribe);
    if (reset && !expired) body.appendChild(h('p', { class: 'plan-meta' }, reset));

    body.appendChild(h('div', { class: 'mt-16' }, progress(percent, tone)));
    body.appendChild(h('div', { class: 'usage-row' },
        h('span', { class: 'strong' }, t('已用 {used} / 总计 {total}', {
            used: formatBytes(used),
            total: formatBytes(total)
        })),
        h('span', {}, t('在线设备 {alive_ip}/{device_limit}', {
            alive_ip: subscribe.alive_ip === undefined ? 0 : subscribe.alive_ip,
            device_limit: subscribe.device_limit === undefined || subscribe.device_limit === null ? '∞' : subscribe.device_limit
        }))
    ));

    body.appendChild(clientIcons(url));
    body.appendChild(subscriptionActions(url));

    const actions = h('div', { class: 'btn-group mt-24' },
        button(t('购买订阅'), { type: 'primary', onClick: () => navigate('/plan'), iconName: 'gift' }),
        button(t('续费'), { onClick: () => navigate('/plan') }),
        subscribe.plan && subscribe.plan.reset_price !== null && subscribe.plan.reset_price !== undefined && !expired
            ? button(t('重置已用流量'), { onClick: options.onResetTraffic })
            : null
    );
    body.appendChild(actions);

    return h('section', { class: 'card' }, head, body);
}

function subscriptionActions(url) {
    const apple = isAppleClientPlatform();
    return h('div', { class: 'btn-group mt-16' },
        copyButton(url, t('复制订阅地址'), {
            onCopied: ok => {
                if (ok && apple) {
                    toast(t('导入Shadowrocket 请通过扫码订阅或专用按钮'), 'info', 3500);
                }
            }
        }),
        button(t('扫码订阅'), {
            iconName: 'qr',
            onClick: () => {
                modal({
                    title: t('订阅二维码'),
                    width: 'narrow',
                    body: h('div', {},
                        apple ? h('div', { class: 'qr-hint' }, t('该二维码仅供Shadowrocket使用')) : null,
                        qrImage(apple ? shadowrocketImportUrl(url) : url, { showContent: false })
                    )
                });
            }
        })
    );
}

function quickLinks(stat) {
    const items = [
        { label: '使用文档', desc: '学习如何使用本站', value: '', icon: 'book', path: '/knowledge' },
        { label: '我的工单', desc: '遇到问题可以通过工单与我们沟通', value: stat && stat[1] !== undefined ? stat[1] : 0, icon: 'ticket', path: '/ticket' },
        { label: '我的邀请', desc: '邀请好友注册，获取推广佣金', value: stat && stat[2] !== undefined ? stat[2] : 0, icon: 'wallet', path: '/invite' },
        { label: '个人中心', desc: '管理账户、钱包与安全设置', value: '', icon: 'user', path: '/profile' }
    ];
    return h('section', { class: 'card' },
        h('div', { class: 'card-head' }, h('h2', { class: 'card-title' }, t('快捷入口'))),
        h('div', { class: 'card-body' },
            h('div', { class: 'quick-grid' },
                items.map(item => h('a', {
                    class: 'quick-item',
                    href: `#${item.path}`
                },
                    h('span', { class: 'qi-icon' }, icon(item.icon, 18)),
                    item.value !== '' ? h('span', { class: 'qi-value' }, String(item.value)) : null,
                    h('span', { class: 'qi-label' }, t(item.label)),
                    h('span', { class: 'qi-desc' }, t(item.desc))
                ))
            )
        )
    );
}

const NOTICE_DISMISS_KEY = 'classic_notice_dismissed';

function noticeTime(notice) {
    return Number(notice.updated_at || notice.created_at || 0);
}

function noticeSignature(notice) {
    const source = [
        notice.id,
        noticeTime(notice),
        notice.title || '',
        notice.content || '',
        notice.tags || ''
    ].join('|');
    let hash = 2166136261;
    for (let index = 0; index < source.length; index += 1) {
        hash ^= source.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }
    return `${notice.id}|${noticeTime(notice)}|${(hash >>> 0).toString(36)}`;
}

function readDismissedNotices() {
    try {
        const value = JSON.parse(localStorage.getItem(NOTICE_DISMISS_KEY) || '{}');
        return value && typeof value === 'object' ? value : {};
    } catch {
        return {};
    }
}

function isNoticeDismissed(notice) {
    const dismissed = readDismissedNotices();
    return dismissed[notice.id] === noticeSignature(notice);
}

function dismissNotice(notice) {
    try {
        const dismissed = readDismissedNotices();
        dismissed[notice.id] = noticeSignature(notice);
        localStorage.setItem(NOTICE_DISMISS_KEY, JSON.stringify(dismissed));
    } catch {
        /* localStorage may be unavailable */
    }
}

function isPopupNotice(notice) {
    return String(notice.tags || '')
        .split(',')
        .map(tagName => tagName.trim())
        .includes('弹窗');
}

function autoShowNotice(notices) {
    const notice = notices.find(item => isPopupNotice(item) && !isNoticeDismissed(item));
    if (notice) showNotice(notice);
}

function noticeBanner(notices) {
    const slides = notices.map(notice => h('button', {
        class: 'notice-slide',
        type: 'button',
        'aria-label': notice.title || t('公告'),
        onClick: () => showNotice(notice)
    },
        h('div', { class: 'notice-slide-inner' },
            h('span', { class: 'notice-badge' }, t('公告')),
            h('div', { class: 'notice-banner-title' }, notice.title || '')
        )
    ));

    const track = h('div', { class: 'notice-track' }, slides);
    const dots = h('div', { class: 'notice-dots' });
    let current = 0;
    let timer = null;
    let dragging = false;
    let swiping = false;
    let startX = 0;
    let startY = 0;
    let deltaX = 0;
    let suppressClick = false;

    const slideWidth = () => track.getBoundingClientRect().width || 1;

    const goTo = index => {
        if (!slides.length) return;
        current = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${current * 100}%)`;
        Array.from(dots.children).forEach((dot, dotIndex) => {
            dot.classList.toggle('active', dotIndex === current);
        });
    };

    const stop = () => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const start = () => {
        if (slides.length <= 1) return;
        stop();
        timer = window.setInterval(() => {
            if (!track.isConnected) {
                stop();
                return;
            }
            goTo(current + 1);
        }, 5000);
    };

    if (slides.length > 1) {
        slides.forEach((slide, index) => {
            dots.appendChild(h('button', {
                class: `notice-dot ${index === 0 ? 'active' : ''}`.trim(),
                type: 'button',
                'aria-label': `${index + 1}`,
                onClick: event => {
                    event.stopPropagation();
                    goTo(index);
                    start();
                }
            }));
        });

        const beginDrag = event => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            dragging = true;
            swiping = false;
            startX = event.clientX;
            startY = event.clientY;
            deltaX = 0;
            track.style.transition = 'none';
            track.classList.add('dragging');
            stop();
        };

        const moveDrag = event => {
            if (!dragging) return;
            const dx = event.clientX - startX;
            const dy = event.clientY - startY;
            if (!swiping) {
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                if (Math.abs(dy) > Math.abs(dx)) {
                    dragging = false;
                    track.classList.remove('dragging');
                    track.style.transition = '';
                    start();
                    return;
                }
                swiping = true;
                if (track.setPointerCapture) {
                    try {
                        track.setPointerCapture(event.pointerId);
                    } catch {
                        /* pointer may already be released */
                    }
                }
            }
            deltaX = dx;
            const width = slideWidth();
            const min = -(slides.length - 1) * width;
            let offset = -current * width + dx;
            if (offset > 0) offset *= 0.3;
            if (offset < min) offset = min + (offset - min) * 0.3;
            track.style.transform = `translateX(${offset}px)`;
        };

        const endDrag = () => {
            if (!dragging) return;
            dragging = false;
            track.classList.remove('dragging');
            track.style.transition = '';
            if (swiping && Math.abs(deltaX) > Math.min(80, slideWidth() * 0.15)) {
                goTo(current + (deltaX < 0 ? 1 : -1));
                suppressClick = true;
                window.setTimeout(() => {
                    suppressClick = false;
                }, 50);
            } else {
                goTo(current);
            }
            deltaX = 0;
            start();
        };

        track.addEventListener('pointerdown', beginDrag);
        track.addEventListener('pointermove', moveDrag);
        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);
        track.addEventListener('click', event => {
            if (!suppressClick) return;
            event.preventDefault();
            event.stopPropagation();
        }, true);

        track.addEventListener('mouseenter', stop);
        track.addEventListener('mouseleave', () => {
            if (!dragging) start();
        });
        start();
    }

    const banner = h('section', { class: 'notice-carousel' }, track, dots);

    if (!slides.length) {
        banner.replaceChildren(emptyBlock({ title: t('暂无数据') }));
    }

    return banner;
}

function showNotice(notice) {
    let controller = null;
    controller = modal({
        title: notice.title || t('公告详情'),
        body: h('div', {},
            h('div', { class: 'small faint mb-12' }, formatDate(noticeTime(notice), true)),
            h('div', { class: 'notice-content' }, raw(notice.content || ''))
        ),
        footer: [
            h('button', {
                class: 'btn left',
                type: 'button',
                onClick: () => {
                    dismissNotice(notice);
                    if (controller) controller.close();
                    toast(t('已设置不再弹出'));
                }
            }, t('不再显示')),
            h('button', {
                class: 'btn',
                type: 'button',
                onClick: () => {
                    if (controller) controller.close();
                }
            }, t('关闭'))
        ]
    });
}

function inlineNotice(type, text, action) {
    const node = alert(type, '', text, action);
    node.classList.add('alert-toast');
    return node;
}

function alertBlocks(stat) {
    const nodes = [];
    if (stat && Number(stat[0]) > 0) {
        nodes.push(inlineNotice('warning', t('您有 {count} 个未支付订单', { count: stat[0] }),
            button(t('立即支付'), { size: 'sm', type: 'primary', onClick: () => navigate('/order') })));
    }
    if (stat && Number(stat[1]) > 0) {
        nodes.push(inlineNotice('info', t('您有 {count} 个工单等待处理', { count: stat[1] }),
            button(t('立即查看'), { size: 'sm', onClick: () => navigate('/ticket') })));
    }
    return nodes;
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());

    const [subscribeResult, statResult, noticeResult] = await Promise.allSettled([
        fetchSubscribe(true),
        api.get('/user/getStat'),
        api.get('/user/notice/fetch', { current: 1, pageSize: 5 })
    ]);

    if (subscribeResult.status === 'rejected') {
        el.replaceChildren(errorBlock(subscribeResult.reason, () => mount(el)));
        return;
    }

    const subscribe = subscribeResult.value;
    const stat = statResult.status === 'fulfilled' && statResult.value ? statResult.value.data : null;
    const notices = noticeResult.status === 'fulfilled' && noticeResult.value
        ? (noticeResult.value.data || []).slice().sort((a, b) => noticeTime(b) - noticeTime(a))
        : [];

    const reload = async () => {
        clearCaches();
        await mount(el);
    };

    const onReset = async () => {
        const ok = await confirmDialog({
            title: t('确定要重置订阅信息？'),
            content: t('重置订阅提示信息'),
            okText: t('重置')
        });
        if (!ok) return;
        try {
            await api.get('/user/resetSecurity');
            toast(t('重置成功'));
            await reload();
        } catch (error) {
            toast(error.message || t('请求失败'), 'error');
        }
    };

    const onResetTraffic = async () => {
        if (!subscribe.plan) return;
        const ok = await confirmDialog({
            title: t('确定重置当前已用流量？'),
            content: t('点击「确定」将会跳转到收银台，支付订单后系统将会清空您当月已使用流量。'),
            okText: t('确定')
        });
        if (!ok) return;
        try {
            const response = await api.post('/user/order/save', {
                plan_id: subscribe.plan.id,
                period: 'reset_price'
            });
            const tradeNo = response ? response.data : '';
            if (tradeNo) navigate(`/order/${tradeNo}`);
            else toast(t('订单已创建'));
        } catch (error) {
            toast(error.message || t('请求失败'), 'error');
        }
    };

    const left = h('div', { class: 'stack' },
        subscriptionCard(subscribe || {}, { onReset, onResetTraffic }),
        quickLinks(stat)
    );

    const layout = h('div', { class: 'stack' },
        noticeBanner(notices),
        ...alertBlocks(stat),
        left
    );

    el.replaceChildren(layout);

    window.setTimeout(() => {
        if (el.isConnected) autoShowNotice(notices);
    }, 300);
}
