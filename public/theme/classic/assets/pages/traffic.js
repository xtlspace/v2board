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
    let deducted = 0;
    rows.forEach(row => {
        const u = Number(row.u || 0);
        const d = Number(row.d || 0);
        const rate = Number(row.server_rate || 1);
        upload += u;
        download += d;
        deducted += (u + d) * rate;
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
            { title: t('记录时间'), width: '170px', className: 'nowrap', cellClass: 'nowrap', render: row => formatDate(row.record_at, true) },
            { title: t('实际上行'), width: '130px', render: row => formatBytes(row.u) },
            { title: t('实际下行'), width: '130px', render: row => formatBytes(row.d) },
            { title: t('扣费倍率'), width: '110px', render: row => `${row.server_rate || '1'}x` },
            {
                title: t('合计'),
                render: row => h('span', { class: 'strong' }, formatBytes((Number(row.u || 0) + Number(row.d || 0)) * Number(row.server_rate || 1)))
            }
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
            extra: h('span', { class: 'small faint' }, `${t('合计')}: ${formatBytes(deducted)}`),
            flush: true,
            body: detail
        })
    ));
}
