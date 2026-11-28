import { h } from '../lib/dom.js';
import { t } from '../lib/i18n.js';
import { api } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import {
    alert, button, card, confirmDialog, emptyBlock, errorBlock, field, loadingBlock, modal, select, toast
} from '../lib/ui.js';

function dialerCard(dialer, onChanged) {
    const rules = (dialer && dialer.rules) || [];
    const inNodes = (dialer && dialer.in_nodes) || [];
    const outNodes = (dialer && dialer.out_nodes) || [];
    const nodeName = (list, id) => {
        const node = list.find(item => Number(item.id) === Number(id));
        return node ? node.name : `#${id}`;
    };

    const openEditor = rule => {
        const inSelect = select(inNodes.map(node => ({ value: String(node.id), label: node.name })), rule ? String(rule.in_node_id) : (inNodes[0] ? String(inNodes[0].id) : ''), null);
        const outSelect = select(outNodes.map(node => ({ value: String(node.id), label: node.name })), rule ? String(rule.out_node_id) : (outNodes[0] ? String(outNodes[0].id) : ''), null);
        const submit = button(t('保存'), {
            type: 'primary',
            onClick: async () => {
                if (!inSelect.value || !outSelect.value) {
                    toast(t('请选择节点'), 'error');
                    return;
                }
                if (inSelect.value === outSelect.value) {
                    toast(t('请选择不同的入口和出口'), 'error');
                    return;
                }
                submit.disabled = true;
                try {
                    await api.post('/user/dialerProxy/saveOne', {
                        id: rule ? rule.id : 0,
                        in_node_id: Number(inSelect.value),
                        out_node_id: Number(outSelect.value)
                    });
                    toast(t('已保存'));
                    controller.close();
                    onChanged();
                } catch (error) {
                    toast(error.message || t('请求失败'), 'error');
                    submit.disabled = false;
                }
            }
        });
        const controller = modal({
            title: rule ? t('编辑规则') : t('添加规则'),
            body: h('div', {},
                field({ label: t('入口节点'), control: inSelect, required: true }),
                field({ label: t('出口节点'), control: outSelect, required: true })
            ),
            footer: [
                h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
                submit
            ]
        });
    };

    const body = h('div', { class: 'dialer-rules' });
    if (!inNodes.length || !outNodes.length) {
        body.appendChild(emptyBlock({
            iconName: 'wifi',
            title: t('暂无节点'),
            desc: t('请联系管理员开启链式代理节点')
        }));
    } else if (!rules.length) {
        body.appendChild(emptyBlock({ iconName: 'wifi', title: t('暂无数据') }));
    } else {
        rules.forEach(rule => {
            body.appendChild(h('div', { class: 'rule-row' },
                h('div', { class: 'rule-main' },
                    h('span', { class: 'tag tag-primary' }, nodeName(inNodes, rule.in_node_id)),
                    h('span', { class: 'rule-arrow' }, '→'),
                    h('span', { class: 'tag tag-success' }, nodeName(outNodes, rule.out_node_id))
                ),
                h('div', { class: 'rule-actions' },
                    button('', { size: 'sm', iconName: 'edit', title: t('编辑'), onClick: () => openEditor(rule) }),
                    button('', {
                        size: 'sm',
                        iconName: 'trash',
                        title: t('删除'),
                        onClick: async () => {
                            const ok = await confirmDialog({
                                title: t('删除'),
                                content: t('确认删除该规则？'),
                                okText: t('删除'),
                                danger: true
                            });
                            if (!ok) return;
                            try {
                                await api.post('/user/dialerProxy/delete', { id: rule.id });
                                toast(t('操作成功'));
                                onChanged();
                            } catch (error) {
                                toast(error.message || t('请求失败'), 'error');
                            }
                        }
                    })
                )
            ));
        });
    }

    return card({
        title: t('规则列表'),
        extra: button(t('添加规则'), {
            size: 'sm',
            type: 'primary',
            iconName: 'plus',
            onClick: () => openEditor(null)
        }),
        flush: true,
        body
    });
}

async function loadDialer() {
    const response = await api.get('/user/dialerProxy');
    return response ? response.data : null;
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());

    const render = async () => {
        let data = null;
        try {
            data = await loadDialer();
        } catch (error) {
            el.replaceChildren(errorBlock(error, () => mount(el)));
            return;
        }

        const header = h('div', {},
            h('h1', { class: 'page-title' }, t('链式代理')),
            h('div', { class: 'page-desc' }, t('配置入口节点与出口节点的代理链路'))
        );

        if (!data || data.enabled === false) {
            el.replaceChildren(h('div', { class: 'stack' },
                header,
                alert('warning', t('链式代理未开启'), t('请联系管理员开启链式代理节点'),
                    button(t('购买订阅'), {
                        size: 'sm',
                        onClick: () => navigate('/plan')
                    }))
            ));
            return;
        }

        el.replaceChildren(h('div', { class: 'stack' },
            header,
            dialerCard(data, render)
        ));
    };

    await render();
}
