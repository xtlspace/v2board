import { h, money } from './dom.js';
import { t } from './i18n.js';
import { api } from './api.js';
import { button, field, input, modal, toast } from './ui.js';

export function transferModal(available, onDone) {
    const amountInput = input({
        type: 'number',
        min: '1',
        step: '0.01',
        placeholder: t('请输入金额')
    });
    const submit = button(t('确认'), {
        type: 'primary',
        onClick: async () => {
            const value = Number(amountInput.value);
            if (!value || value <= 0) {
                toast(t('请输入金额'), 'error');
                return;
            }
            const cents = Math.round(value * 100);
            if (cents > available) {
                toast(t('可用佣金不足'), 'error');
                return;
            }
            submit.disabled = true;
            try {
                await api.post('/user/transfer', { transfer_amount: cents });
                toast(t('操作成功'));
                controller.close();
                onDone();
            } catch (error) {
                toast(error.message || t('请求失败'), 'error');
                submit.disabled = false;
            }
        }
    });

    const controller = modal({
        title: t('推广佣金划转至余额'),
        body: h('div', {},
            h('p', { class: 'muted' }, t('划转后的余额仅用于{title}消费使用', { title: window.settings.title || 'V2Board' })),
            field({
                label: t('当前推广佣金余额'),
                control: h('div', { class: 'plan-name', style: { fontSize: '22px', margin: 0 } }, money(available))
            }),
            field({
                label: t('划转金额'),
                control: amountInput,
                hint: `${t('可用佣金')}: ${money(available)}`
            })
        ),
        footer: [
            h('button', { class: 'btn', type: 'button', onClick: () => controller.close() }, t('取消')),
            submit
        ]
    });
}
