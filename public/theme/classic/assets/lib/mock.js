/**
 * In-memory API used by the static preview (`preview.html`).
 * Production requests never touch this file.
 */

const DAY = 86400;
const now = Math.floor(Date.now() / 1000);

const GB = 1024 * 1024 * 1024;
const NOTICE_IMG = 'https://images.unsplash.com/photo-1515405295579-ba7b45403062?auto=format&fit=crop&w=1400&q=70';

const clone = value => JSON.parse(JSON.stringify(value));
const state = {
    user: {
        email: 'demo@example.com',
        transfer_enable: 100 * GB,
        device_limit: 3,
        last_login_at: now - 3600,
        created_at: now - 200 * DAY,
        banned: 0,
        auto_renewal: 0,
        remind_expire: 1,
        remind_traffic: 1,
        expired_at: now + 36 * DAY,
        balance: 12680,
        commission_balance: 4850,
        plan_id: 1,
        discount: null,
        commission_rate: 15,
        telegram_id: null,
        uuid: '8c1f0f7a-2c9f-4a6a-9f4b-2f6fa0a1b2c3',
        avatar_url: 'https://cravatar.cn/avatar/0f8b2f1c9f0a2d6e?d=identicon&s=64'
    },
    subscribe: {
        plan_id: 1,
        token: 'demo_subscribe_token',
        expired_at: now + 36 * DAY,
        u: 35 * GB,
        d: 46 * GB,
        transfer_enable: 100 * GB,
        device_limit: 3,
        email: 'demo@example.com',
        uuid: '8c1f0f7a-2c9f-4a6a-9f4b-2f6fa0a1b2c3',
        plan: {
            id: 1,
            name: '标准套餐',
            transfer_enable: 100 * GB,
            device_limit: 3,
            speed_limit: null,
            content: '<p>适合日常办公与流媒体使用，支持全部节点。</p>',
            reset_price: 500
        },
        alive_ip: 2,
        subscribe_url: 'https://example.com/api/v1/client/subscribe?token=demo_subscribe_token',
        reset_day: 7,
        reset_hour: null,
        reset_at: now + 7 * DAY,
        allow_new_period: 1,
        renew_reset_traffic: 1,
        surplus_enable: 1,
        surplus_amount: 1260
    },
    notices: [
        {
            id: 3,
            title: '节点线路维护通知',
            content: '<p>我们将在本周日凌晨 02:00 - 04:00 对香港节点进行维护，期间可能出现短暂断连，请提前做好准备。</p><p>感谢您的理解与支持。</p>',
            tags: '维护,节点,弹窗',
            img_url: NOTICE_IMG,
            created_at: now - 2 * 3600,
            updated_at: 1795678260
        },
        {
            id: 2,
            title: '新增新加坡 BGP 节点',
            content: '<p>新加坡 BGP 节点已上线，延迟更低，欢迎体验。</p><ul><li>入口：新加坡</li><li>倍率：1.0x</li></ul>',
            tags: '上新',
            img_url: NOTICE_IMG,
            created_at: now - 2 * DAY,
            updated_at: 1795500000
        },
        {
            id: 1,
            title: '关于防范账号共享的说明',
            content: '<p>为保障所有用户的体验，系统会检测异常多设备同时在线行为。请勿将订阅地址分享给他人。</p>',
            tags: '公告',
            img_url: NOTICE_IMG,
            created_at: now - 6 * DAY,
            updated_at: 1795000000
        }
    ],
    plans: [
        {
            id: 1,
            group_id: 1,
            transfer_enable: 100 * GB,
            device_limit: 3,
            name: '标准套餐',
            speed_limit: null,
            show: 1,
            sort: 1,
            renew: 1,
            content: '<p>适合日常办公、流媒体与轻度下载。</p><ul><li>100 GB 高速流量</li><li>最多 3 台设备</li><li>全部地区节点</li></ul>',
            month_price: 1500,
            quarter_price: 4000,
            half_year_price: 7500,
            year_price: 14000,
            two_year_price: null,
            three_year_price: null,
            onetime_price: null,
            reset_price: 500,
            capacity_limit: null
        },
        {
            id: 2,
            group_id: 1,
            transfer_enable: 500 * GB,
            device_limit: 5,
            name: '高级套餐',
            speed_limit: 500,
            show: 1,
            sort: 2,
            renew: 1,
            content: '<p>面向重度用户，含专属高速线路。</p><ul><li>500 GB 高速流量</li><li>最多 5 台设备</li><li>500 Mbps 限速</li><li>专属 IEPL 线路</li></ul>',
            month_price: 3000,
            quarter_price: 8500,
            half_year_price: 16000,
            year_price: 30000,
            two_year_price: 56000,
            three_year_price: 78000,
            onetime_price: null,
            reset_price: 1000,
            capacity_limit: 50
        },
        {
            id: 3,
            group_id: 1,
            transfer_enable: 200 * GB,
            device_limit: 1,
            name: '流量包',
            speed_limit: null,
            show: 1,
            sort: 3,
            renew: 0,
            content: '<p>一次性流量包，不限制使用时长，用完为止。</p>',
            month_price: null,
            quarter_price: null,
            half_year_price: null,
            year_price: null,
            two_year_price: null,
            three_year_price: null,
            onetime_price: 1999,
            reset_price: null,
            capacity_limit: null
        }
    ],
    orders: [
        {
            id: 4,
            trade_no: '202611250001',
            plan_id: 2,
            period: 'month_price',
            total_amount: 3000,
            status: 0,
            created_at: now - 2 * 3600,
            plan: { id: 2, name: '高级套餐' }
        },
        {
            id: 3,
            trade_no: '202611010002',
            plan_id: 1,
            period: 'quarter_price',
            total_amount: 4000,
            status: 3,
            created_at: now - 20 * DAY,
            plan: { id: 1, name: '标准套餐' }
        },
        {
            id: 2,
            trade_no: '202609180003',
            plan_id: 1,
            period: 'month_price',
            total_amount: 1500,
            status: 2,
            created_at: now - 68 * DAY,
            plan: { id: 1, name: '标准套餐' }
        },
        {
            id: 1,
            trade_no: '202608150004',
            plan_id: 3,
            period: 'onetime_price',
            total_amount: 1999,
            status: 4,
            created_at: now - 100 * DAY,
            plan: { id: 3, name: '流量包' }
        }
    ],
    servers: [
        { id: 11, name: '香港 01 | BGP', tags: '香港,CN2', rate: '1', is_online: 1, last_check_at: now - 40, protocol: 'vless', sort: 1, dialer_proxy_in: 1, dialer_proxy_out: 1 },
        { id: 12, name: '香港 02 | IEPL', tags: '香港,IEPL', rate: '1.2', is_online: 1, last_check_at: now - 70, protocol: 'shadowsocks', sort: 2, dialer_proxy_in: 1, dialer_proxy_out: 1 },
        { id: 13, name: '日本 01 | 东京', tags: '日本,直连', rate: '1', is_online: 0, last_check_at: now - 3600, protocol: 'vless', sort: 3, dialer_proxy_in: 0, dialer_proxy_out: 1 },
        { id: 14, name: '新加坡 01 | BGP', tags: '新加坡,低延迟', rate: '1.5', is_online: 1, last_check_at: now - 55, protocol: 'vless', sort: 4, dialer_proxy_in: 0, dialer_proxy_out: 1 },
        { id: 15, name: '美国 01 | 洛杉矶', tags: '美国,流媒体', rate: '2', is_online: 1, last_check_at: now - 90, protocol: 'anytls', sort: 5, dialer_proxy_in: 0, dialer_proxy_out: 0 }
    ],
    tickets: [
        {
            id: 2,
            subject: '无法连接香港节点',
            level: 1,
            status: 0,
            reply_status: 1,
            created_at: now - 5 * 3600,
            updated_at: now - 3 * 3600,
            messages: [
                { id: 1, user_id: 1, is_me: true, message: '今天下午开始香港 01 节点一直连接超时，麻烦帮忙看一下。', created_at: now - 5 * 3600 },
                { id: 2, user_id: 0, is_me: false, message: '您好，香港 01 节点刚才进行了线路切换，请更新订阅后重试。如果仍然无法连接，请把客户端日志发给我们。', created_at: now - 3 * 3600 }
            ]
        },
        {
            id: 1,
            subject: '如何配置 Clash',
            level: 0,
            status: 1,
            reply_status: 1,
            created_at: now - 30 * DAY,
            updated_at: now - 28 * DAY,
            messages: [
                { id: 3, user_id: 1, is_me: true, message: '请问 Clash 应该怎么导入订阅？', created_at: now - 30 * DAY },
                { id: 4, user_id: 0, is_me: false, message: '在 Clash 的 Profiles 页面粘贴订阅地址并下载即可，具体可以参考使用文档。', created_at: now - 28 * DAY }
            ]
        }
    ],
    knowledge: {
        入门指南: [
            { id: 1, category: '入门指南', title: '第一次使用：从注册到连接', updated_at: now - 3 * DAY },
            { id: 2, category: '入门指南', title: '订阅地址是什么，如何保管', updated_at: now - 8 * DAY }
        ],
        客户端配置: [
            { id: 3, category: '客户端配置', title: 'Clash Verge 配置教程', updated_at: now - 1 * DAY },
            { id: 4, category: '客户端配置', title: 'Shadowrocket 配置教程', updated_at: now - 4 * DAY },
            { id: 5, category: '客户端配置', title: 'Sing-box 配置教程', updated_at: now - 10 * DAY }
        ],
        常见问题: [
            { id: 6, category: '常见问题', title: '为什么节点显示离线', updated_at: now - 2 * DAY }
        ]
    },
    knowledgeBodies: {
        1: '<h3>第一步：注册账号</h3><p>使用邮箱注册并登录，进入「购买订阅」选择适合您的套餐。</p><h3>第二步：导入订阅</h3><p>在仪表盘复制订阅地址：</p><pre><code>{{subscribeUrl}}</code></pre><p>将地址粘贴到客户端即可。当前站点：{{siteName}}</p><h3>第三步：开始使用</h3><p>选择一个延迟较低的节点，开启代理即可。</p>',
        2: '<p>订阅地址包含您的账号凭证，请勿分享给他人。</p><p>如果发生泄露，可以在仪表盘点击「重置订阅」重新生成。</p>',
        3: '<h3>Clash Verge</h3><ol><li>打开 Profiles 页面</li><li>粘贴订阅地址并点击 Import</li><li>选择导入的配置并启用</li></ol><p>订阅地址：<code>{{subscribeUrl}}</code></p>',
        4: '<h3>Shadowrocket</h3><ol><li>点击右上角 +</li><li>类型选择 Subscribe</li><li>粘贴订阅地址并保存</li></ol>',
        5: '<h3>Sing-box</h3><p>下载配置文件后导入客户端：</p><pre><code>{{subscribeUrl}}</code></pre>',
        6: '<p>节点状态每 5 分钟更新一次，短暂离线通常会自动恢复。如果长时间离线，请尝试更新订阅或联系客服。</p>'
    },
    traffic: [
        { record_at: now - 1 * DAY, u: 1.2 * GB, d: 4.6 * GB, server_rate: '1' },
        { record_at: now - 2 * DAY, u: 0.8 * GB, d: 3.1 * GB, server_rate: '1' },
        { record_at: now - 3 * DAY, u: 2.1 * GB, d: 6.4 * GB, server_rate: '1.2' },
        { record_at: now - 4 * DAY, u: 0.4 * GB, d: 1.8 * GB, server_rate: '1' },
        { record_at: now - 5 * DAY, u: 1.6 * GB, d: 5.2 * GB, server_rate: '1.5' }
    ],
    invite: {
        codes: [
            { code: 'DEMO2026', pv: 18, status: 0 },
            { code: 'WELCOME8', pv: 6, status: 0 }
        ],
        stat: [12, 3680, 920, 15, 4850],
        details: [
            { id: 3, trade_no: '202611200011', order_amount: 3000, get_amount: 450, created_at: now - 4 * DAY },
            { id: 2, trade_no: '202611020012', order_amount: 4000, get_amount: 600, created_at: now - 20 * DAY },
            { id: 1, trade_no: '202609200013', order_amount: 1500, get_amount: 225, created_at: now - 60 * DAY }
        ]
    },
    commConfig: {
        is_telegram: 1,
        telegram_discuss_link: 'https://t.me/example_group',
        stripe_pk: '',
        withdraw_methods: ['支付宝', 'USDT-TRC20'],
        withdraw_close: 0,
        currency: 'CNY',
        currency_symbol: '\u00a5',
        commission_distribution_enable: 1,
        commission_distribution_l1: 15,
        commission_distribution_l2: 5,
        commission_distribution_l3: 2
    },
    guestConfig: {
        tos_url: 'https://example.com/tos',
        is_email_verify: 0,
        is_invite_force: 0,
        email_whitelist_suffix: 0,
        is_recaptcha: 0,
        recaptcha_site_key: '',
        app_description: '稳定、快速的网络加速服务',
        app_url: 'https://example.com',
        logo: ''
    },
    sessions: [
        { session_id: 'sess-current', ip: '203.0.113.24', ua: 'Chrome 131 / Windows', login_at: now - 3600, is_current: true },
        { session_id: 'sess-phone', ip: '198.51.100.8', ua: 'Safari / iPhone', login_at: now - 2 * DAY, is_current: false }
    ],
    dialer: {
        enabled: true,
        in_nodes: [{ id: 11, name: '香港 01 | BGP' }, { id: 12, name: '香港 02 | IEPL' }],
        out_nodes: [{ id: 13, name: '日本 01 | 东京' }, { id: 14, name: '新加坡 01 | BGP' }],
        rules: [{ id: 1, in_node_id: 11, out_node_id: 14 }]
    }
};

if (typeof window !== 'undefined') {
    const previewParams = new URLSearchParams(window.location.search);
    const noSub = previewParams.get('nosub') === '1';

    if (noSub) {
        Object.assign(state.subscribe, {
            plan_id: null,
            plan: null,
            expired_at: null,
            u: 0,
            d: 0,
            transfer_enable: 0,
            device_limit: null,
            alive_ip: 0,
            reset_day: null,
            reset_hour: null,
            reset_at: null
        });
        Object.assign(state.user, {
            plan_id: null,
            expired_at: null,
            transfer_enable: 0,
            u: 0,
            d: 0
        });
    }

    const trafficParam = previewParams.get('traffic');
    if (!noSub && trafficParam) {
        const total = Number(state.subscribe.transfer_enable || 0);
        let used = 0;
        if (trafficParam === 'full' || Number(trafficParam) >= 100) {
            used = total;
        } else {
            const percent = Math.max(0, Math.min(99, Number(trafficParam) || 0));
            used = Math.round(total * (percent / 100));
        }
        state.subscribe.u = used;
        state.subscribe.d = 0;
        state.user.u = used;
        state.user.d = 0;
    }

    if (!noSub && previewParams.get('expired') === '1') {
        state.subscribe.expired_at = now - 86400;
        state.user.expired_at = now - 86400;
    }
}

function fail(message) {
    const error = new Error(message);
    error.name = 'ApiError';
    throw error;
}

function page(rows, data) {
    const current = Math.max(1, Number(data && data.current ? data.current : 1));
    const pageSize = Math.max(1, Number(data && data.page_size ? data.page_size : 10));
    const total = rows.length;
    const items = rows.slice((current - 1) * pageSize, current * pageSize);
    return { data: clone(items), total };
}

function findOrder(tradeNo) {
    return state.orders.find(order => String(order.trade_no) === String(tradeNo));
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function response(payload) {
    return delay(120 + Math.random() * 160).then(() => clone(payload));
}

export async function mockRequest(method, path, data = {}) {
    const query = data || {};

    if (path === '/passport/auth/login' || path === '/passport/auth/register') {
        return response({ data: { token: state.user.uuid.replace(/-/g, ''), is_admin: 0, auth_data: 'mock-auth-data' } });
    }
    if (path === '/passport/auth/token2Login') {
        return response({ data: { token: 'mock', is_admin: 0, auth_data: 'mock-auth-data' } });
    }
    if (path === '/passport/auth/forget') {
        return response({ data: true });
    }
    if (path === '/passport/comm/sendEmailVerify') {
        return response({ data: true });
    }
    if (path === '/guest/comm/config') {
        return response({ data: state.guestConfig });
    }
    if (path === '/user/comm/config') {
        return response({ data: state.commConfig });
    }
    if (path === '/user/info') {
        return response({ data: state.user });
    }
    if (path === '/user/getStat') {
        const unpaid = state.orders.filter(order => order.status === 0).length;
        const openTickets = state.tickets.filter(ticket => ticket.status === 0).length;
        return response({ data: [unpaid, openTickets, state.invite.stat[0]] });
    }
    if (path === '/user/getSubscribe') {
        return response({ data: state.subscribe });
    }
    if (path === '/user/notice/fetch') {
        if (query.id) {
            const notice = state.notices.find(item => String(item.id) === String(query.id));
            if (!notice) fail('Notice not found');
            return response({ data: notice });
        }
        return response({ data: state.notices, total: state.notices.length });
    }
    if (path === '/user/plan/fetch') {
        if (query.id) {
            const plan = state.plans.find(item => String(item.id) === String(query.id));
            if (!plan) fail('Subscription plan does not exist');
            return response({ data: plan });
        }
        return response({ data: state.plans.filter(plan => plan.show) });
    }
    if (path === '/user/order/fetch') {
        let orders = state.orders;
        if (query.status !== undefined && query.status !== null && query.status !== '') {
            orders = orders.filter(order => String(order.status) === String(query.status));
        }
        return response({ data: orders });
    }
    if (path === '/user/order/detail') {
        const order = findOrder(query.trade_no);
        if (!order) fail('Order does not exist or has been paid');
        const plan = state.plans.find(item => item.id === order.plan_id) || { id: 0, name: '充值' };
        return response({
            data: {
                ...order,
                plan,
                discount_amount: order.status === 3 ? 300 : null,
                balance_amount: order.status === 3 ? 500 : null,
                handling_amount: null,
                try_out_plan_id: 0
            }
        });
    }
    if (path === '/user/order/getPaymentMethod') {
        return response({
            data: [
                { id: 1, name: '支付宝', payment: 'Epay', icon: '', handling_fee_fixed: 0, handling_fee_percent: 0 },
                { id: 2, name: '微信支付', payment: 'EpayWX', icon: '', handling_fee_fixed: 0, handling_fee_percent: 1.5 },
                { id: 3, name: 'USDT', payment: 'BEasyPaymentUSDT', icon: '', handling_fee_fixed: 0, handling_fee_percent: 0 }
            ]
        });
    }
    if (path === '/user/order/save') {
        if (Number(query.plan_id) === 0) {
            const amount = Number(query.deposit_amount || 0);
            if (!amount || amount <= 0) fail('Deposit amount must be greater than 0');
            const tradeNo = `20261125${String(state.orders.length + 1).padStart(4, '0')}`;
            state.orders.unshift({
                id: state.orders.length + 20,
                trade_no: tradeNo,
                plan_id: 0,
                period: 'deposit',
                total_amount: amount,
                status: 0,
                created_at: now,
                plan: { id: 0, name: '充值' }
            });
            return response({ data: tradeNo });
        }
        const plan = state.plans.find(item => String(item.id) === String(query.plan_id));
        if (!plan) fail('Subscription plan does not exist');
        const tradeNo = `20261125${String(state.orders.length + 1).padStart(4, '0')}`;
        const price = plan[query.period];
        if (price === null || price === undefined) fail('This payment period cannot be purchased, please choose another period');
        state.orders.unshift({
            id: state.orders.length + 10,
            trade_no: tradeNo,
            plan_id: plan.id,
            period: query.period,
            total_amount: price,
            status: 0,
            created_at: now,
            plan: { id: plan.id, name: plan.name }
        });
        return response({ data: tradeNo });
    }
    if (path === '/user/order/checkout') {
        return response({ type: 0, data: 'https://example.com/mock-payment' });
    }
    if (path === '/user/order/cancel') {
        const order = findOrder(query.trade_no);
        if (!order) fail('Order does not exist');
        order.status = 2;
        return response({ data: true });
    }
    if (path === '/user/order/check') {
        const order = findOrder(query.trade_no);
        return response({ data: order ? order.status : 0 });
    }
    if (path === '/user/server/fetch') {
        return response({ data: state.servers });
    }
    if (path === '/user/ticket/fetch') {
        if (query.id) {
            const ticket = state.tickets.find(item => String(item.id) === String(query.id));
            if (!ticket) fail('Ticket does not exist');
            return response({ data: ticket });
        }
        return response({ data: state.tickets });
    }
    if (path === '/user/ticket/save') {
        const ticket = {
            id: state.tickets.length + 10,
            subject: query.subject,
            level: Number(query.level),
            status: 0,
            reply_status: 0,
            created_at: now,
            updated_at: now,
            messages: [{ id: Date.now(), user_id: 1, is_me: true, message: query.message, created_at: now }]
        };
        state.tickets.unshift(ticket);
        return response({ data: true });
    }
    if (path === '/user/ticket/reply') {
        const ticket = state.tickets.find(item => String(item.id) === String(query.id));
        if (!ticket) fail('Ticket does not exist');
        ticket.messages.push({ id: Date.now(), user_id: 1, is_me: true, message: query.message, created_at: now });
        ticket.reply_status = 0;
        ticket.updated_at = now;
        return response({ data: true });
    }
    if (path === '/user/ticket/close') {
        const ticket = state.tickets.find(item => String(item.id) === String(query.id));
        if (!ticket) fail('Ticket does not exist');
        ticket.status = 1;
        return response({ data: true });
    }
    if (path === '/user/ticket/withdraw') {
        return response({ data: true });
    }
    if (path === '/user/knowledge/fetch') {
        if (query.id) {
            const all = Object.values(state.knowledge).flat();
            const article = all.find(item => String(item.id) === String(query.id));
            if (!article) fail('Article does not exist');
            const body = (state.knowledgeBodies[article.id] || '')
                .replace(/\{\{siteName\}\}/g, 'V2Board')
                .replace(/\{\{subscribeUrl\}\}/g, state.subscribe.subscribe_url);
            return response({ data: { ...article, body } });
        }
        let result = {};
        const keyword = (query.keyword || '').trim().toLowerCase();
        Object.keys(state.knowledge).forEach(category => {
            const rows = state.knowledge[category].filter(item => !keyword || item.title.toLowerCase().includes(keyword));
            if (rows.length) result[category] = rows;
        });
        return response({ data: result });
    }
    if (path === '/user/stat/getTrafficLog') {
        return response({ data: state.traffic });
    }
    if (path === '/user/invite/fetch') {
        return response({ data: { codes: state.invite.codes, stat: state.invite.stat } });
    }
    if (path === '/user/invite/save') {
        const code = Math.random().toString(36).slice(2, 10).toUpperCase();
        state.invite.codes.push({ code, pv: 0, status: 0 });
        return response({ data: true });
    }
    if (path === '/user/invite/details') {
        return response(page(state.invite.details, query));
    }
    if (path === '/user/telegram/getBotInfo') {
        return response({ data: { username: 'demo_board_bot' } });
    }
    if (path === '/user/unbindTelegram') {
        state.user.telegram_id = null;
        return response({ data: true });
    }
    if (path === '/user/resetSecurity') {
        state.subscribe.token = `token_${Math.random().toString(36).slice(2, 10)}`;
        state.subscribe.subscribe_url = `https://example.com/api/v1/client/subscribe?token=${state.subscribe.token}`;
        return response({ data: state.subscribe.subscribe_url });
    }
    if (path === '/user/update') {
        Object.assign(state.user, query);
        return response({ data: true });
    }
    if (path === '/user/changePassword') {
        return response({ data: true });
    }
    if (path === '/user/redeemgiftcard') {
        return response({ data: true });
    }
    if (path === '/user/transfer') {
        const amount = Number(query.transfer_amount || 0);
        if (!amount || amount <= 0) fail('The transfer amount parameter is wrong');
        if (amount > Number(state.user.commission_balance || 0)) fail('Insufficient commission balance');
        state.user.commission_balance = Number(state.user.commission_balance || 0) - amount;
        state.user.balance = Number(state.user.balance || 0) + amount;
        return response({ data: true });
    }
    if (path === '/user/getActiveSession') {
        return response({ data: state.sessions });
    }
    if (path === '/user/removeActiveSession') {
        state.sessions = state.sessions.filter(item => item.session_id !== query.session_id);
        return response({ data: true });
    }
    if (path === '/user/coupon/check') {
        if (!query.code) fail('Coupon cannot be empty');
        if (String(query.code).toUpperCase() === 'DEMO10') {
            return response({ data: { id: 1, code: 'DEMO10', type: 2, value: 10, show: 1 } });
        }
        if (String(query.code).toUpperCase() === 'SAVE5') {
            return response({ data: { id: 2, code: 'SAVE5', type: 1, value: 500, show: 1 } });
        }
        fail('Invalid coupon');
    }
    if (path === '/user/dialerProxy/fetch') {
        return response({ data: state.dialer });
    }
    if (path === '/user/dialerProxy/saveOne') {
        const inId = Number(query.in_node_id);
        const outId = Number(query.out_node_id);
        const index = state.dialer.rules.findIndex(rule => Number(rule.id) === Number(query.id));
        const rule = { id: index >= 0 ? Number(query.id) : Date.now(), in_node_id: inId, out_node_id: outId };
        if (index >= 0) state.dialer.rules[index] = rule;
        else state.dialer.rules.push(rule);
        return response({ data: { id: rule.id } });
    }
    if (path === '/user/dialerProxy/delete') {
        state.dialer.rules = state.dialer.rules.filter(rule => Number(rule.id) !== Number(query.id));
        return response({ data: true });
    }
    if (path === '/user/comm/getStripePublicKey') {
        fail('Stripe is not configured in preview');
    }

    fail(`Mock endpoint not implemented: ${method} ${path}`);
}
