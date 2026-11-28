import { h, formatBytes, formatDate } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api, fetchSubscribe } from '../lib/api.js';
import { alert, card, emptyBlock, errorBlock, loadingBlock, progress, table } from '../lib/ui.js';

export async function mount(el) {
    el.replaceChildren(loadingBlock());

    let subscribe = null;
    let rows = [];
    try {
        const [subscribeResponse, logResponse] = await Promise.all([
            fetchSubscribe(true),
            api.get('/user/stat/getTrafficLog')
        ]);
        subscribe = subscribeResponse;
        rows = (logResponse && logResponse.data) || [];
    } catch (error) {
        el.replaceChildren(errorBlock(error, () => mount(el)));
        return;
    }

    const used = Number(subscribe && subscribe.u ? subscribe.u : 0) + Number(subscribe && subscribe.d ? subscribe.d : 0);
    const total = Number(subscribe && subscribe.transfer_enable ? subscribe.transfer_enable : 0);
    const percent = total ? Math.min(100, (used / total) * 100) : 0;

    let upload = 0;
    let download = 0;
    rows.forEach(row => {
        upload += Number(row.u || 0);
        download += Number(row.d || 0);
    });

    const summary = h('div', { class: 'stat-grid mb-16' },
        h('div', { class: 'stat-card' },
            h('div', { class: 'sc-label' }, t('已用流量')),
            h('div', { class: 'sc-value' }, formatBytes(used)),
            h('div', { class: 'mt-12' }, progress(percent, percent >= 99 ? 'danger' : percent >= 80 ? 'warning' : ''))
        ),
        h('div', { class: 'stat-card' },
            h('div', { class: 'sc-label' }, t('剩余流量')),
            h('div', { class: 'sc-value' }, formatBytes(Math.max(0, total - used)))
        ),
        h('div', { class: 'stat-card' },
            h('div', { class: 'sc-label' }, t('总流量')),
            h('div', { class: 'sc-value' }, formatBytes(total))
        ),
        h('div', { class: 'stat-card' },
            h('div', { class: 'sc-label' }, t('上传 / 下载')),
            h('div', { class: 'sc-value', style: { fontSize: '16px' } },
                `${formatBytes(upload)} / ${formatBytes(download)}`)
        )
    );

    const detail = table({
        columns: [
            { title: t('记录时间'), width: '30%', className: 'nowrap', cellClass: 'nowrap', render: row => formatDate(row.record_at, true) },
            { title: t('实际上行'), width: '24%', className: 'align-right', cellClass: 'align-right nowrap', render: row => formatBytes(row.u) },
            { title: t('实际下行'), width: '24%', className: 'align-right', cellClass: 'align-right nowrap', render: row => formatBytes(row.d) },
            { title: t('扣费倍率'), width: '22%', className: 'align-right', cellClass: 'align-right nowrap', render: row => `${row.server_rate || '1'}x` }
        ],
        rows,
        rowKey: 'record_at',
        empty: emptyBlock({ iconName: 'chart', title: t('暂无记录') })
    });

    el.replaceChildren(h('div', { class: 'stack' },
        h('div', { class: 'flex-between flex-wrap' },
            h('div', {},
                h('h1', { class: 'page-title' }, t('流量明细')),
                h('div', { class: 'page-desc' }, t('流量明细仅保留近月数据以供查询。'))
            )
        ),
        summary,
        alert('info', '', t('公式：(实际上行 + 实际下行) x 扣费倍率 = 扣除流量')),
        card({
            title: t('流量明细'),
            flush: true,
            body: detail
        })
    ));
}
