import { h, formatDate } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import {
    alert, button, card, confirmDialog, emptyBlock, errorBlock, field, input, levelTag,
    loadingBlock, modal, select, table, textarea, ticketStatus, toast
} from '../lib/ui.js';

function newTicketModal(onCreated) {
    const subject = input({ placeholder: t('请输入工单主题'), maxlength: '120' });
    const level = select([
        { value: '0', label: t('低') },
        { value: '1', label: t('中') },
        { value: '2', label: t('高') }
    ], '0');
    const message = textarea({ placeholder: t('请描述你遇到的问题'), rows: 6 });

    const submit = button(t('提交'), {
        type: 'primary',
        onClick: async () => {
            const subjectValue = subject.value.trim();
            const messageValue = message.value.trim();
            if (!subjectValue) {
                toast(t('请输入工单主题'), 'error');
                subject.focus();
                return;
            }
            if (!messageValue) {
                toast(t('请描述你遇到的问题'), 'error');
                message.focus();
                return;
            }
            submit.disabled = true;
            try {
                await api.post('/user/ticket/save', {
                    subject: subjectValue,
                    level: Number(level.value),
                    message: messageValue
                });
                toast(t('工单已创建'));
                controller.close();
                onCreated();
            } catch (error) {
                toast(error.message || t('请求失败'), 'error', 3200);
                submit.disabled = false;
            }
        }
    });

    const controller = modal({
        title: t('新的工单'),
        body: h('div', {},
            field({ label: t('主题'), control: subject, required: true }),
            field({ label: t('工单级别'), control: level, required: true }),
            field({ label: t('消息'), control: message, required: true })
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());

    const render = async () => {
        let tickets = [];
        try {
            const response = await api.get('/user/ticket/fetch');
            tickets = (response && response.data) || [];
        } catch (error) {
            el.replaceChildren(errorBlock(error, () => mount(el)));
            return;
        }

        const rows = table({
            columns: [
                { title: '#', width: '60px', render: ticket => h('span', { class: 'faint' }, String(ticket.id)) },
                { title: t('主题'), className: 'nowrap', cellClass: 'nowrap', render: ticket => h('a', { href: `#/ticket/${ticket.id}` }, ticket.subject) },
                { title: t('工单级别'), width: '100px', render: ticket => levelTag(ticket.level) },
                { title: t('工单状态'), width: '100px', render: ticket => ticketStatus(ticket) },
                { title: t('创建时间'), width: '170px', className: 'nowrap', render: ticket => formatDate(ticket.created_at, true) },
                { title: t('最后回复'), width: '170px', className: 'nowrap', render: ticket => formatDate(ticket.updated_at, true) },
                {
                    title: t('操作'),
                    width: '150px',
                    className: 'align-right',
                    cellClass: 'align-right',
                    render: ticket => h('div', { class: 'cell-actions end' },
                        button(t('查看'), {
                            size: 'sm',
                            onClick: () => navigate(`/ticket/${ticket.id}`)
                        }),
                        button(t('关闭'), {
                            size: 'sm',
                            type: 'danger',
                            disabled: Number(ticket.status) === 1,
                            onClick: async () => {
                                const ok = await confirmDialog({
                                    title: t('关闭工单'),
                                    content: t('确定关闭该工单？'),
                                    okText: t('确认'),
                                    danger: true
                                });
                                if (!ok) return;
                                try {
                                    await api.post('/user/ticket/close', { id: ticket.id });
                                    toast(t('工单已关闭'));
                                    render();
                                } catch (error) {
                                    toast(error.message || t('请求失败'), 'error');
                                }
                            }
                        })
                    )
                }
            ],
            rows: tickets,
            rowKey: 'id',
            empty: emptyBlock({
                iconName: 'ticket',
                title: t('还没有工单'),
                desc: t('遇到问题可以通过工单与我们沟通'),
                action: button(t('新的工单'), {
                    type: 'primary',
                    onClick: () => newTicketModal(render)
                })
            })
        });

        el.replaceChildren(h('div', { class: 'stack' },
            h('div', { class: 'flex-between flex-wrap' },
                h('div', {},
                    h('h1', { class: 'page-title' }, t('我的工单')),
                    h('div', { class: 'page-desc' }, t('遇到问题可以通过工单与我们沟通'))
                ),
                h('div', { class: 'btn-group' },
                    button(t('新的工单'), {
                        type: 'primary',
                        iconName: 'plus',
                        onClick: () => newTicketModal(render)
                    })
                )
            ),
            card({ title: t('工单历史'), flush: true, body: rows })
        ));
    };

    await render();
}

export async function mountDetail(el, ctx) {
    el.replaceChildren(loadingBlock());
    const id = ctx.params.id;

    const render = async () => {
        let ticket = null;
        try {
            const response = await api.get('/user/ticket/fetch', { id });
            ticket = response ? response.data : null;
        } catch (error) {
            el.replaceChildren(errorBlock(error, () => mountDetail(el, ctx)));
            return;
        }
        if (!ticket) {
            el.replaceChildren(errorBlock(new Error(t('工单不存在')), () => mountDetail(el, ctx)));
            return;
        }

        const closed = Number(ticket.status) === 1;
        const messages = h('div', { class: 'ticket-messages' });
        (ticket.message || ticket.messages || []).forEach(message => {
            const mine = Boolean(message.is_me);
            messages.appendChild(h('div', { class: `ticket-msg ${mine ? 'mine' : 'support'}` },
                h('span', { class: 'avatar' }, mine ? t('我') : t('客服')),
                h('div', { class: 'msg-main' },
                    h('div', { class: 'msg-meta' },
                        h('span', {}, formatDate(message.created_at, true))
                    ),
                    h('div', { class: 'msg-bubble' }, message.message)
                )
            ));
        });

        let replyBox = null;
        if (!closed) {
            const replyInput = textarea({ placeholder: t('输入内容回复工单...'), rows: 4 });
            const send = button(t('发送'), {
                type: 'primary',
                iconName: 'send',
                onClick: async () => {
                    const content = replyInput.value.trim();
                    if (!content) {
                        toast(t('消息不能为空'), 'error');
                        replyInput.focus();
                        return;
                    }
                    send.disabled = true;
                    try {
                        await api.post('/user/ticket/reply', { id, message: content });
                        toast(t('回复成功'));
                        replyInput.value = '';
                        send.disabled = false;
                        render();
                    } catch (error) {
                        toast(error.message || t('请求失败'), 'error');
                        send.disabled = false;
                    }
                }
            });
            replyBox = h('div', { class: 'ticket-reply' },
                replyInput,
                h('div', { class: 'btn-group mt-12' }, send)
            );
        } else {
            replyBox = alert('info', t('已关闭'), t('工单已关闭，无法回复'));
        }

        el.replaceChildren(h('div', { class: 'stack' },
            h('div', { class: 'flex-between flex-wrap' },
                h('div', {},
                    h('h1', { class: 'page-title' }, ticket.subject),
                    h('div', { class: 'page-desc' },
                        ticketStatus(ticket),
                        h('span', { class: 'faint' }, ' · '),
                        t('工单级别'),
                        levelTag(ticket.level)
                    )
                ),
                h('div', { class: 'btn-group' },
                    button(t('返回列表'), { iconName: 'arrow-left', onClick: () => navigate('/ticket') }),
                    !closed ? button(t('关闭工单'), {
                        type: 'danger',
                        onClick: async () => {
                            const ok = await confirmDialog({
                                title: t('关闭工单'),
                                content: t('确定关闭该工单？'),
                                okText: t('确认'),
                                danger: true
                            });
                            if (!ok) return;
                            try {
                                await api.post('/user/ticket/close', { id });
                                toast(t('工单已关闭'));
                                render();
                            } catch (error) {
                                toast(error.message || t('请求失败'), 'error');
                            }
                        }
                    }) : null
                )
            ),
            card({
                title: t('消息'),
                extra: h('div', { class: 'small faint' }, `${t('创建时间')}: ${formatDate(ticket.created_at, true)}`),
                body: h('div', {}, messages, replyBox)
            })
        ));
    };

    await render();
}
