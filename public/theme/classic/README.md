# classic 主题（可读源码版）

`public/theme/classic` 是按原编译版 `default` 主题的界面复刻的**纯前端源码主题**。
不使用 Umi / React，不需要 Node 构建，改完文件刷新页面即可生效。

支持简体中文与英文，主题色、边栏/顶栏明暗、背景图等沿用 V2Board 原有的主题配置项。

---

## 1. 目录结构

```
public/theme/classic/
├─ config.json              # 主题配置项（后台主题设置里读取）
├─ dashboard.blade.php      # 入口模板，注入 window.settings
├─ preview.html             # 本地静态预览入口
├─ preview-server.js        # 零依赖预览服务器
└─ assets/
   ├─ app.css               # 设计令牌 + 组件 + 页面样式
   ├─ app.js                # 入口：hash 路由 + 页面挂载
   ├─ layout.js             # 侧边栏 / 顶栏 / 页脚
   ├─ lib/
   │  ├─ api.js             # /api/v1 请求封装、登录态、缓存
   │  ├─ dom.js             # DOM 构建、图标、格式化、复制
   │  ├─ i18n.js            # 中英文文案（复用原主题词典 + 补充键）
   │  ├─ mock.js            # 预览用假接口
   │  ├─ router.js          # hash 路由
   │  └─ ui.js              # 卡片 / 表格 / 弹窗 / 表单 / Toast 等
   ├─ pages/                # 页面模块
   │  ├─ auth.js  login.js  register.js  forget.js
   │  ├─ dashboard.js  plan.js  order.js  node.js
   │  ├─ ticket.js  knowledge.js  traffic.js
   │  ├─ invite.js  profile.js
   │  └─ dialer.js
   ├─ i18n/zh-CN.js en-US.js
   ├─ fonts/                # Simple Line Icons 字体
   └─ images/icon/          # 客户端图标
```

## 2. 启用主题

后台「系统设置 → 主题设置」把前台主题切换为 `classic`，或直接修改
`config/v2board.php`：

```php
'frontend_theme' => 'classic',
```

首次访问时会由 `ThemeService` 自动生成 `config/theme/classic.php`。

> 回退：把 `frontend_theme` 改回 `default` 即可，原编译版文件未做任何修改。

`public/theme/.gitignore` 已放行 `classic` 目录，提交时不会被忽略。

## 3. 本地预览（无需 PHP）

本机有 Node 即可：

```bash
node public/theme/classic/preview-server.js
# http://localhost:4173/preview.html
```

预览使用 `assets/lib/mock.js` 的假数据，不会请求真实接口。可选参数：

| 参数 | 说明 |
| --- | --- |
| `?guest=1` | 清空登录态，查看登录页 |
| `?lang=en-US` | 英文界面 |
| `?color=green` | `default` / `green` / `black` / `darkblue` |
| `?sidebar=dark` | 边栏明暗 |
| `?header=light` | 顶栏明暗 |

示例：`http://localhost:4173/preview.html?guest=1&lang=en-US`

## 4. 已实现功能

- 登录 / 注册（邮箱验证码、邀请码、服务条款、reCAPTCHA）/ 忘记密码
- 快捷登录：兼容 `/#/login?verify=xxx&redirect=dashboard`
- 仪表盘：顶部紧凑公告条（无背景图，多公告自动轮播，支持鼠标/触屏左右滑动）、带「弹窗」标签的公告自动弹出、支持「不再显示」（内容或更新时间变化后会再次弹出）、订阅卡片、到期时间、流量进度、在线设备、复制订阅地址/扫码订阅、重置订阅、重置已用流量、未支付订单与工单提醒；Clash 与 Shadowrocket 按钮直接调起客户端导入订阅
- 顶部右侧直接显示当前账号邮箱，点击后仅提供「个人中心 / 退出登录」
- 购买订阅：套餐筛选、周期选择、优惠券校验、余额提示、创建订单；页面顶部显示当前余额，后台开启折抵方案时显示可折抵金额
- 订单：列表/筛选、详情、继续支付、取消；支付方式（跳转、二维码、余额抵扣、Stripe 卡支付）
- 节点状态：在线状态、倍率、标签、搜索；右上角提供「链式代理」入口
- 链式代理：独立页面，侧边栏「订阅」组、节点状态下方，管理入口/出口节点规则
- 工单：列表、新建、回复、关闭
- 使用文档：分类、搜索、文章详情（支持后台 `{{subscribeUrl}}` 等变量）
- 流量明细：已用/剩余/总流量、按日记录、扣费倍率
- 我的邀请：邀请码、邀请链接、佣金统计、划转、提现、佣金发放记录
- 个人中心：账户信息、钱包（余额充值/佣金划转/自动续费开关）、礼品卡、通知开关、改密、重置订阅、活跃会话、Telegram 绑定；不展示订阅地址

## 5. 与原版的一致性

- 沿用 `#/xxx` hash 路由，支付回跳 `/#/order/<trade_no>`、快捷登录链接均可直接工作。
- 登录态仍是 `localStorage.authorization`，请求头 `authorization` 直接携带 JWT，与后端 `App\Http\Middleware\User` 完全兼容。
- 复用原主题 `assets/i18n/zh-CN.js`、`en-US.js` 词典，并在 `lib/i18n.js` 补充本主题新增文案。
- 视觉令牌取自原主题：主色默认黑色（可选 `default` / `green` / `black` / `darkblue`）、侧边栏 `#35393e`、选中项 `#1d2023`、顶栏 `#343a40`、背景 `#f0f3f8`、卡片头 `#f8f9fc`、边框 `#e4e9f3`、进度条 `#82b54b`、字号 13/14px、圆角 2–4px。
- 侧边栏顺序、宽度（250px）、顶栏高度（52px）、内容区宽度（1200px）按线上主题对齐。
- 使用文档页采用线上同款布局：顶部整行搜索 + 按分类分组的文档列表。

## 6. 二次开发

新增页面：

1. 在 `assets/pages/` 新建模块，导出 `mount(el, ctx)`；
2. 在 `assets/app.js` 中 `import` 并 `defineRoute({...})`；
3. 需要侧边栏高亮时，把路由的 `active` 设为 `layout.js` 中对应菜单项的 `key`。

约定：

- 所有接口调用走 `lib/api.js` 的 `api.get/post`，不要直接 `fetch`。
- 接口数据一律用文本节点渲染；只有后台可信 HTML（套餐内容、公告、文档）使用 `lib/dom.js` 的 `raw()`。
- 金额单位是「分」，展示统一用 `money()`；流量用 `formatBytes()`。

## 7. 已知限制

- 二维码使用 `api.qrserver.com` 生成；若站点要求完全离线，可替换 `lib/ui.js` 的 `qrImage()` 为本地二维码库。
- Stripe 使用 Stripe.js v3 的 `createToken` + 后端 `Sources/Charges`，与现有 `StripeCredit` 支付插件匹配；未配置 Stripe 时选择该方式会提示。
- 邮件模板、支付回调等仍由后端负责，本主题不涉及。
- 管理面板仍是原编译版 `public/assets/admin`，未在本次改造范围内。
