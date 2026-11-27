import React, { useEffect, useRef, useState } from 'react'
import { Tabs, Switch, Input, Select, Button, Space, message, Alert } from 'antd'
import { api } from '../api/client'

const { TextArea } = Input

function ConfigRow({ title, description, children, childrenClassName = '' }) {
  return (
    <div className="row v2board-config-row" style={{ padding: '20px', borderBottom: '1px solid #eee' }}>
      <div className="col-lg-6">
        <div style={{ fontWeight: 'bold', marginBottom: 5 }}>{title}</div>
        <div style={{ fontSize: 12, marginBottom: 5, color: '#666' }}>{description}</div>
      </div>
      <div className={`col-lg-6 text-right ${childrenClassName}`}>{children}</div>
    </div>
  )
}

const wrap = (value) => (typeof value === 'number' ? String(value) : value || '')

export default function ConfigSystem() {
  const [config, setConfig] = useState({})
  const [plans, setPlans] = useState([])
  const [emailTemplates, setEmailTemplates] = useState([])
  const [themeTemplates, setThemeTemplates] = useState([])
  const [loading, setLoading] = useState(true)
  const [tab, setTab] = useState('site')
  const timerRef = useRef(null)

  const load = () => {
    setLoading(true)
    Promise.all([
      api.get('/config/fetch'),
      api.get('/plan/fetch'),
      api.get('/config/getEmailTemplate'),
      api.get('/config/getThemeTemplate')
    ]).then(([configRes, planRes, emailRes, themeRes]) => {
      setLoading(false)
      if (configRes.code === 200) setConfig(configRes.data || {})
      if (planRes.code === 200) setPlans(planRes.data || [])
      if (emailRes.code === 200) setEmailTemplates(emailRes.data || [])
      if (themeRes.code === 200) setThemeTemplates((themeRes.data || []).filter((t) => !String(t).startsWith('.')))
    })
  }

  useEffect(() => {
    load()
    return () => {
      if (timerRef.current) clearTimeout(timerRef.current)
    }
  }, [])

  const set = (section, key, value) => {
    setConfig((prev) => ({ ...prev, [section]: { ...(prev[section] || {}), [key]: value } }))
    if (timerRef.current) clearTimeout(timerRef.current)
    timerRef.current = setTimeout(() => {
      api.post('/config/save', { [key]: value }).then((res) => {
        if (res.code === 200) message.success({ content: '保存成功', key: 'config-save', duration: 1 })
      })
    }, 700)
  }

  const setLocal = (section, key, value) => {
    setConfig((prev) => ({ ...prev, [section]: { ...(prev[section] || {}), [key]: value } }))
  }

  const saveField = (key, value) => {
    api.post('/config/save', { [key]: value }).then((res) => {
      if (res.code === 200) message.success({ content: '保存成功', key: 'config-save', duration: 1 })
    })
  }

  const site = config.site || {}
  const safe = config.safe || {}
  const subscribe = config.subscribe || {}
  const deposit = config.deposit || {}
  const ticket = config.ticket || {}
  const invite = config.invite || {}
  const frontend = config.frontend || {}
  const server = config.server || {}
  const email = config.email || {}
  const telegram = config.telegram || {}
  const app = config.app || {}

  const input = (section, key, data, placeholder, type = 'text') => (
    <Input
      type={type}
      value={wrap(data[key])}
      placeholder={placeholder}
      onChange={(e) => set(section, key, e.target.value)}
    />
  )

  const switchBox = (section, key, data) => (
    <Switch checked={!!Number(data[key])} onChange={(checked) => set(section, key, checked ? 1 : 0)} />
  )

  const select = (section, key, data, options, placeholder = '请选择') => (
    <Select
      style={{ width: '100%', textAlign: 'left' }}
      value={data[key] === null || data[key] === undefined ? undefined : data[key]}
      placeholder={placeholder}
      onChange={(value) => set(section, key, value)}
      options={options}
    />
  )

  const withdrawMethods = Array.isArray(invite.commission_withdraw_method) ? invite.commission_withdraw_method : []
  const whitelist = Array.isArray(safe.email_whitelist_suffix) ? safe.email_whitelist_suffix : []
  const bonus = Array.isArray(deposit.deposit_bounus) ? deposit.deposit_bounus : []

  return (
    <div className="block block-rounded border-bottom">
      <Tabs
        activeKey={tab}
        onChange={setTab}
        size="large"
        tabPosition="top"
        className="v2board-config-tabs"
        items={[
          {
            key: 'site',
            label: '站点',
            children: (
              <div>
                <ConfigRow title="站点名称" description="用于显示需要站点名称的地方。">
                  {input('site', 'app_name', site, '请输入站点名称')}
                </ConfigRow>
                <ConfigRow title="站点描述" description="用于显示需要站点描述的地方。">
                  {input('site', 'app_description', site, '请输入站点描述')}
                </ConfigRow>
                <ConfigRow title="站点网址" description="当前网站最新网址，将会在邮件等需要用于网址处体现。">
                  {input('site', 'app_url', site, '请输入站点URL，末尾不要/')}
                </ConfigRow>
                <ConfigRow title="强制HTTPS" description="当站点没有使用HTTPS，CDN或反代开启强制HTTPS时需要开启。">
                  {switchBox('site', 'force_https', site)}
                </ConfigRow>
                <ConfigRow title="LOGO" description="用于显示需要LOGO的地方。">
                  {input('site', 'logo', site, '请输入LOGO URL，末尾不要/')}
                </ConfigRow>
                <ConfigRow title="订阅URL" description="用于订阅所使用，留空则为站点URL。如需多个订阅URL随机获取请使用逗号进行分割。">
                  <TextArea rows={4} value={wrap(site.subscribe_url)} placeholder="请输入订阅URL，末尾不要/。逗号分割支持多域名" onChange={(e) => set('site', 'subscribe_url', e.target.value)} />
                </ConfigRow>
                <ConfigRow title="订阅路径" description="用于订阅所使用，留空则为/api/v1/client/subscribe。如需更换不同的订阅路径请设置。">
                  {input('site', 'subscribe_path', site, '/api/v1/client/subscribe')}
                </ConfigRow>
                <ConfigRow title="用户条款(TOS)URL" description="用于跳转到用户条款(TOS)">
                  {input('site', 'tos_url', site, '请输入用户条款URL，末尾不要/')}
                </ConfigRow>
                <ConfigRow title="停止新用户注册" description="开启后任何人都将无法进行注册。">
                  {switchBox('site', 'stop_register', site)}
                </ConfigRow>
                <ConfigRow title="注册试用" description="选择需要试用的订阅，如果没有选项请先前往订阅管理添加。">
                  <Select
                    style={{ width: '100%' }}
                    value={Number(site.try_out_plan_id) || 0}
                    onChange={(value) => set('site', 'try_out_plan_id', value)}
                    options={[{ value: 0, label: '关闭' }, ...plans.map((p) => ({ value: p.id, label: p.name }))]}
                  />
                </ConfigRow>
                <ConfigRow title="试用时间(小时)" description="注册试用订阅的可用时长。">
                  {input('site', 'try_out_hour', site, '请输入')}
                </ConfigRow>
                <ConfigRow title="货币单位" description="仅用于展示使用，更改后系统中所有的货币单位都将发生变更。">
                  {input('site', 'currency', site, '如 CNY')}
                </ConfigRow>
                <ConfigRow title="货币符号" description="仅用于展示使用。">
                  {input('site', 'currency_symbol', site, '如 ¥')}
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'safe',
            label: '安全',
            children: (
              <div>
                <ConfigRow title="邮箱验证" description="开启后将会强制要求用户进行邮箱验证。">
                  {switchBox('safe', 'email_verify', safe)}
                </ConfigRow>
                <ConfigRow title="禁止使用Gmail多别名" description="开启后Gmail多别名将无法注册。">
                  {switchBox('safe', 'email_gmail_limit_enable', safe)}
                </ConfigRow>
                <ConfigRow title="安全模式" description="开启后除了站点URL以外的绑定本站点的域名访问都将会被403。">
                  {switchBox('safe', 'safe_mode_enable', safe)}
                </ConfigRow>
                <ConfigRow title="后台路径" description="后台管理路径，修改后将会改变原有的 admin 路径，当前页面会失效，需要使用新路径重新访问。">
                  <Input
                    value={wrap(safe.secure_path)}
                    placeholder="至少8位字母或数字"
                    onChange={(e) => setLocal('safe', 'secure_path', e.target.value)}
                    onBlur={(e) => {
                      const value = e.target.value
                      if (value && value !== safe.secure_path) message.warning('后台路径已修改，请使用新路径重新访问')
                      saveField('secure_path', value)
                    }}
                  />
                </ConfigRow>
                <ConfigRow title="邮箱后缀白名单" description="开启后在名单中的邮箱后缀才允许进行注册。">
                  <Space>
                    {switchBox('safe', 'email_whitelist_enable', safe)}
                    <Select
                      mode="tags"
                      style={{ minWidth: 280 }}
                      value={whitelist}
                      placeholder="请输入后缀域名，逗号分割 如 gmail.com"
                      onChange={(value) => set('safe', 'email_whitelist_suffix', value)}
                    />
                  </Space>
                </ConfigRow>
                <ConfigRow title="防机器人" description="开启后将会使用 Google reCAPTCHA 防止机器人。">
                  {switchBox('safe', 'recaptcha_enable', safe)}
                </ConfigRow>
                <ConfigRow title="密钥" description="在 Google reCAPTCHA 申请的密钥。">
                  {input('safe', 'recaptcha_key', safe, '请输入')}
                </ConfigRow>
                <ConfigRow title="网站密钥" description="在 Google reCAPTCHA 申请的网站密钥。">
                  {input('safe', 'recaptcha_site_key', safe, '请输入')}
                </ConfigRow>
                <ConfigRow title="IP注册限制" description="开启后如果IP注册账户达到规则要求将会被限制注册。">
                  {switchBox('safe', 'register_limit_by_ip_enable', safe)}
                </ConfigRow>
                <ConfigRow title="次数" description="达到注册次数后开启惩罚。">
                  {input('safe', 'register_limit_count', safe, '请输入')}
                </ConfigRow>
                <ConfigRow title="惩罚时间(分钟)" description="需要等待惩罚时间过后才可以再次注册。">
                  {input('safe', 'register_limit_expire', safe, '请输入')}
                </ConfigRow>
                <ConfigRow title="防爆破限制" description="开启后如果该账户尝试登陆失败次数过多将会被限制。">
                  {switchBox('safe', 'password_limit_enable', safe)}
                </ConfigRow>
                <ConfigRow title="次数" description="达到失败次数后开启惩罚。">
                  {input('safe', 'password_limit_count', safe, '请输入')}
                </ConfigRow>
                <ConfigRow title="惩罚时间(分钟)" description="需要等待惩罚时间过后才可以再次登陆。">
                  {input('safe', 'password_limit_expire', safe, '请输入')}
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'subscribe',
            label: '订阅',
            children: (
              <div>
                <ConfigRow title="允许用户更改订阅" description="开启后用户将会可以对订阅计划进行变更。">
                  {switchBox('subscribe', 'plan_change_enable', subscribe)}
                </ConfigRow>
                <ConfigRow title="月流量重置方式" description="全局流量重置方式，默认每月1号，可以在订阅管理为订阅单独设置。">
                  {select('subscribe', 'reset_traffic_method', subscribe, [
                    { value: 0, label: '每月1号' },
                    { value: 1, label: '按月重置' },
                    { value: 2, label: '不重置' },
                    { value: 3, label: '每年1月1日' },
                    { value: 4, label: '按年重置' }
                  ])}
                </ConfigRow>
                <ConfigRow title="开启折抵方案" description="开启后用户更换订阅将会由系统对原有订阅进行折抵。">
                  {switchBox('subscribe', 'surplus_enable', subscribe)}
                </ConfigRow>
                <ConfigRow title="允许提前开启流量周期" description="开启后用户流量用尽时可以选择扣除订阅时长为代价重置流量。">
                  {switchBox('subscribe', 'allow_new_period', subscribe)}
                </ConfigRow>
                <ConfigRow title="启用链式代理" description="开启后用户可在节点状态页配置链式代理入口/出口，仅 Mihomo 内核可用。">
                  {switchBox('subscribe', 'dialer_proxy_enable', subscribe)}
                </ConfigRow>
                {[
                  ['new_order_event_id', '当订阅新购时触发事件', '新购订阅完成时将触发该任务。'],
                  ['renew_order_event_id', '当订阅续费时触发事件', '续费订阅完成时将触发该任务。'],
                  ['change_order_event_id', '当订阅变更时触发事件', '变更订阅完成时将触发该任务。']
                ].map(([key, title, description]) => (
                  <ConfigRow key={key} title={title} description={description}>
                    {select('subscribe', key, subscribe, [
                      { value: 0, label: '不执行任何动作' },
                      { value: 1, label: '重置用户流量' }
                    ])}
                  </ConfigRow>
                ))}
                <ConfigRow title="在订阅中展示订阅信息" description="开启后将会在用户订阅节点时输出订阅信息。">
                  {switchBox('subscribe', 'show_info_to_server_enable', subscribe)}
                </ConfigRow>
                <ConfigRow title="订阅链接生效模式" description="用户获取订阅链接后的有效期。">
                  {select('subscribe', 'show_subscribe_method', subscribe, [
                    { value: 0, label: '永久有效' },
                    { value: 1, label: '一次性有效' },
                    { value: 2, label: '限时有效' }
                  ])}
                </ConfigRow>
                {Number(subscribe.show_subscribe_method) === 2 ? (
                  <ConfigRow title="订阅链接有效时间(分钟)" description="订阅链接获取后经过该时间将失效。">
                    {input('subscribe', 'show_subscribe_expire', subscribe, '请输入')}
                  </ConfigRow>
                ) : null}
                <ConfigRow title="订阅提醒节点" description="开启后，未购买订阅 / 已过期 / 流量用尽的用户拉订阅时，仅输出一条提醒节点，不再输出完整节点。">
                  {switchBox('subscribe', 'sub_reminder_enable', subscribe)}
                </ConfigRow>
                <ConfigRow title="未购买提醒名称" description="请填写未购买订阅用户拉订阅时的节点名称。">
                  {input('subscribe', 'sub_reminder_name_no_sub', subscribe, '您尚未购买订阅')}
                </ConfigRow>
                <ConfigRow title="已过期提醒名称" description="请填写已过期用户拉订阅时的节点名称。">
                  {input('subscribe', 'sub_reminder_name_expired', subscribe, '您的订阅已过期')}
                </ConfigRow>
                <ConfigRow title="流量用尽提醒名称" description="请填写流量用尽用户拉订阅时的节点名称。">
                  {input('subscribe', 'sub_reminder_name_exhaust', subscribe, '您的流量已用尽')}
                </ConfigRow>
                <ConfigRow title="提醒节点配置" description="参考 v2node 节点字段的 JSON，host 与 port 必填，uuid 留空则使用用户自身 uuid；留空或配置不完整时保持原有行为。">
                  <TextArea rows={6} value={wrap(subscribe.sub_reminder_node)} placeholder='{"host":"1.2.3.4","port":443,"protocol":"vless","uuid":"","tls":1,"network":"tcp"}' onChange={(e) => set('subscribe', 'sub_reminder_node', e.target.value)} />
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'deposit',
            label: '充值',
            children: (
              <div>
                <ConfigRow title="充值奖励" description="充值一定金额可以获得的奖励。">
                  <TextArea
                    rows={3}
                    value={bonus.join(',')}
                    placeholder={'请输入 充值金额:奖励金额，逗号分割\n如 50:18,100:38,200:88'}
                    onChange={(e) => set('deposit', 'deposit_bounus', e.target.value.split(',').filter((v) => v !== ''))}
                  />
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'ticket',
            label: '工单',
            children: (
              <div>
                <ConfigRow title="工单设置" description="请选择工单的状态。">
                  {select('ticket', 'ticket_status', ticket, [
                    { value: 0, label: '完全开放工单' },
                    { value: 1, label: '仅限有付费订单用户' },
                    { value: 2, label: '完全禁止工单' }
                  ])}
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'invite',
            label: '邀请&佣金',
            children: (
              <div>
                <ConfigRow title="开启强制邀请" description="开启后只有被邀请的用户才可以进行注册。">
                  {switchBox('invite', 'invite_force', invite)}
                </ConfigRow>
                <ConfigRow title="邀请佣金百分比" description="用户通过邀请链接注册并购买后，邀请人可获得的佣金比例。">
                  {input('invite', 'invite_commission', invite, '请输入')}
                </ConfigRow>
                <ConfigRow title="用户可创建邀请码上限" description="每个用户最多可创建的邀请码数量。">
                  {input('invite', 'invite_gen_limit', invite, '请输入')}
                </ConfigRow>
                <ConfigRow title="邀请码永不失效" description="开启后邀请码使用后不会失效。">
                  {switchBox('invite', 'invite_never_expire', invite)}
                </ConfigRow>
                <ConfigRow title="佣金仅首次发放" description="开启后佣金仅在用户首次购买时发放。">
                  {switchBox('invite', 'commission_first_time_enable', invite)}
                </ConfigRow>
                <ConfigRow title="佣金自动确认" description="开启后订单完成时自动确认佣金。">
                  {switchBox('invite', 'commission_auto_check_enable', invite)}
                </ConfigRow>
                <ConfigRow title="提现单申请门槛(元)" description="达到该金额才可申请提现。">
                  {input('invite', 'commission_withdraw_limit', invite, '请输入')}
                </ConfigRow>
                <ConfigRow title="提现方式" description="可用的提现方式。">
                  <Select
                    mode="tags"
                    style={{ width: '100%' }}
                    value={withdrawMethods}
                    onChange={(value) => set('invite', 'commission_withdraw_method', value)}
                    placeholder="输入后回车添加，如 支付宝 / USDT / Paypal"
                  />
                </ConfigRow>
                <ConfigRow title="关闭提现" description="开启后用户无法申请提现。">
                  {switchBox('invite', 'withdraw_close_enable', invite)}
                </ConfigRow>
                <ConfigRow title="三级分销" description="开启后佣金将按三级邀请关系分配。">
                  {switchBox('invite', 'commission_distribution_enable', invite)}
                </ConfigRow>
                <ConfigRow title="一级邀请人比例" description="一级邀请人获得的佣金比例。">
                  {input('invite', 'commission_distribution_l1', invite, '请输入')}
                </ConfigRow>
                <ConfigRow title="二级邀请人比例" description="二级邀请人获得的佣金比例。">
                  {input('invite', 'commission_distribution_l2', invite, '请输入')}
                </ConfigRow>
                <ConfigRow title="三级邀请人比例" description="三级邀请人获得的佣金比例。">
                  {input('invite', 'commission_distribution_l3', invite, '请输入')}
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'frontend',
            label: '个性化',
            children: (
              <div>
                <ConfigRow title="边栏风格" description="前台边栏颜色风格。">
                  {select('frontend', 'frontend_theme_sidebar', frontend, [
                    { value: 'light', label: '浅色' },
                    { value: 'dark', label: '深色' }
                  ])}
                </ConfigRow>
                <ConfigRow title="头部风格" description="前台头部颜色风格。">
                  {select('frontend', 'frontend_theme_header', frontend, [
                    { value: 'light', label: '浅色' },
                    { value: 'dark', label: '深色' }
                  ])}
                </ConfigRow>
                <ConfigRow title="主题色" description="前台主题配色。">
                  {select('frontend', 'frontend_theme_color', frontend, [
                    { value: 'default', label: '默认(蓝色)' },
                    { value: 'green', label: '奶绿色' },
                    { value: 'black', label: '黑色' },
                    { value: 'darkblue', label: '暗蓝色' }
                  ])}
                </ConfigRow>
                <ConfigRow title="背景" description="请输入背景图片URL。">
                  {input('frontend', 'frontend_background_url', frontend, '请输入背景图片URL')}
                </ConfigRow>
                <ConfigRow title="前台主题" description="可选主题目录来自 public/theme。">
                  {select('frontend', 'frontend_theme', frontend, themeTemplates.map((t) => ({ value: t, label: t })))}
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'server',
            label: '节点',
            children: (
              <div>
                <ConfigRow title="节点对接API地址" description="v2node 节点一键对接专用地址。">
                  {input('server', 'server_api_url', server, 'https://example.com')}
                </ConfigRow>
                <ConfigRow title="通讯密钥" description="与节点通讯的密钥，长度需大于16位。">
                  {input('server', 'server_token', server, '请输入通讯密钥')}
                </ConfigRow>
                <ConfigRow title="节点拉取动作轮询间隔" description="节点拉取配置的间隔时间（秒）。">
                  {input('server', 'server_pull_interval', server, '60')}
                </ConfigRow>
                <ConfigRow title="节点推送动作轮询间隔" description="节点推送数据的间隔时间（秒）。">
                  {input('server', 'server_push_interval', server, '60')}
                </ConfigRow>
                <ConfigRow title="节点用户流量上报最低阈值" description="每次推送动作仅累计使用流量高于阈值的用户信息会被上报，未上报流量会累计。">
                  {input('server', 'server_node_report_min_traffic', server, '0')}
                </ConfigRow>
                <ConfigRow title="节点用户设备数统计最低阈值" description="低于该流量的设备不会被统计。">
                  {input('server', 'server_device_online_min_traffic', server, '0')}
                </ConfigRow>
                <ConfigRow title="全局设备数限制采用宽松模式" description="开启后设备数统计更宽松。">
                  {switchBox('server', 'device_limit_mode', server)}
                </ConfigRow>
                <ConfigRow title="节点IP不计入设备数量" description="开启后统计设备时将忽略对接节点的IP地址，即链式转发只记1台设备。">
                  {switchBox('server', 'device_limit_chain_forward', server)}
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'email',
            label: '邮件',
            children: (
              <div>
                <Alert
                  type="info"
                  showIcon
                  className="m-3"
                  message="如果你更改了本页配置，需要对队列服务进行重启。另外本页配置优先级高于 .env 中邮件配置。"
                />
                <ConfigRow title="邮件模板" description="选择邮件模板。">
                  {select('email', 'email_template', email, emailTemplates.map((t) => ({ value: t, label: t })))}
                </ConfigRow>
                <ConfigRow title="SMTP服务器地址" description="由邮件服务商提供的服务地址">
                  {input('email', 'email_host', email, '请输入')}
                </ConfigRow>
                <ConfigRow title="SMTP服务端口" description="常见的端口有 25, 465, 587">
                  {input('email', 'email_port', email, '请输入')}
                </ConfigRow>
                <ConfigRow title="SMTP加密方式" description="465端口加密方式一般为SSL，587端口加密方式一般为TLS">
                  {select('email', 'email_encryption', email, [
                    { value: 'ssl', label: 'SSL' },
                    { value: 'tls', label: 'TLS' },
                    { value: 'starttls', label: 'STARTTLS' },
                    { value: 'smtps', label: 'SMTPS' },
                    { value: 'none', label: '无' }
                  ])}
                </ConfigRow>
                <ConfigRow title="SMTP账号" description="由邮件服务商提供的账号">
                  {input('email', 'email_username', email, '请输入')}
                </ConfigRow>
                <ConfigRow title="SMTP密码" description="由邮件服务商提供的密码">
                  <Input.Password value={wrap(email.email_password)} onChange={(e) => set('email', 'email_password', e.target.value)} />
                </ConfigRow>
                <ConfigRow title="发件地址" description="发件人邮箱地址">
                  {input('email', 'email_from_address', email, '请输入')}
                </ConfigRow>
                <ConfigRow title="发送测试邮件" description="向当前管理员邮箱发送一封测试邮件。">
                  <Button
                    onClick={() => {
                      api.post('/config/testSendMail').then((res) => {
                        if (res.code === 200) message.success('测试邮件已提交')
                      })
                    }}
                  >
                    发送测试邮件
                  </Button>
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'telegram',
            label: 'Telegram',
            children: (
              <div>
                <ConfigRow title="启用 Telegram 机器人" description="开启后可使用 Telegram 机器人功能。">
                  {switchBox('telegram', 'telegram_bot_enable', telegram)}
                </ConfigRow>
                <ConfigRow title="机器人 Token" description="从 @BotFather 获取的 Token。">
                  {input('telegram', 'telegram_bot_token', telegram, '请输入 Token')}
                </ConfigRow>
                <ConfigRow title="群组地址" description="Telegram 群组或频道链接。">
                  {input('telegram', 'telegram_discuss_link', telegram, 'https://t.me/xxx')}
                </ConfigRow>
                <ConfigRow title="收款通知" description="开启后收到付款时发送 Telegram 通知。">
                  {switchBox('telegram', 'payment_notify_enable', telegram)}
                </ConfigRow>
                <ConfigRow title="设置Webhook" description="将机器人回调地址设置为当前站点。">
                  <Button
                    onClick={() => {
                      api.post('/config/setTelegramWebhook', { telegram_bot_token: telegram.telegram_bot_token }).then((res) => {
                        if (res.code === 200) message.success('Webhook 已设置')
                      })
                    }}
                  >
                    设置Webhook
                  </Button>
                </ConfigRow>
              </div>
            )
          },
          {
            key: 'app',
            label: 'APP',
            children: (
              <div>
                {[
                  ['windows', 'Windows'],
                  ['macos', 'macOS'],
                  ['android', 'Android']
                ].map(([key, label]) => (
                  <React.Fragment key={key}>
                    <ConfigRow title={`${label} 版本`} description={`客户端 ${label} 版本号。`}>
                      {input('app', `${key}_version`, app, '请输入版本号')}
                    </ConfigRow>
                    <ConfigRow title={`${label} 下载地址`} description={`客户端 ${label} 下载地址。`}>
                      {input('app', `${key}_download_url`, app, '请输入下载地址')}
                    </ConfigRow>
                  </React.Fragment>
                ))}
              </div>
            )
          }
        ]}
      />
    </div>
  )
}
