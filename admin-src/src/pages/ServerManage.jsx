import React, { useEffect, useMemo, useState } from 'react'
import { Button, Input, Select, Switch, Space, message, Drawer, Tag, Badge, Tooltip, Dropdown, Menu } from 'antd'
import {
  PlusOutlined,
  CaretDownOutlined,
  EditOutlined,
  CopyOutlined,
  DeleteOutlined,
  MenuOutlined,
  UserOutlined,
  QuestionCircleOutlined
} from '@ant-design/icons'
import { api } from '../api/client'
import { DataTable } from '../components/ui'
import { copyText } from '../utils'

const { TextArea } = Input

const protocolMeta = {
  shadowsocks: { name: 'Shadowsocks', color: '#489851' },
  vless: { name: 'VLess', color: '#4080FF' },
  hysteria: { name: 'Hysteria', color: '#1A1A1A' },
  hysteria2: { name: 'Hysteria2', color: '#1A1A1A' },
  tuic: { name: 'Tuic', color: '#9400D3' },
  anytls: { name: 'AnyTLS', color: '#FF8C00' }
}

const statusMap = { 0: 'error', 1: 'warning', 2: 'processing' }
const protocolOptions = ['anytls', 'hysteria2', 'shadowsocks', 'tuic', 'vless']

const networkPlaceholder = {
  tcp: JSON.stringify(
    {
      acceptProxyProtocol: false,
      header: {
        type: 'http',
        request: {
          path: ['/'],
          headers: { Host: ['www.baidu.com', 'www.bing.com'] }
        },
        response: {}
      }
    },
    null,
    2
  ),
  http: JSON.stringify({ acceptProxyProtocol: false, path: '/', Host: 'xtls.github.io' }, null, 2),
  ws: JSON.stringify({ acceptProxyProtocol: false, path: '/', headers: { Host: 'xtls.github.io' } }, null, 2),
  grpc: JSON.stringify({ serviceName: 'GunService' }, null, 2),
  httpupgrade: JSON.stringify({ acceptProxyProtocol: false, path: '/', host: 'xtls.github.io' }, null, 2),
  xhttp: JSON.stringify({ path: '/', host: 'xtls.github.io', mode: 'auto', extra: {} }, null, 2)
}

const paddingPlaceholder = JSON.stringify(
  [
    'stop=8',
    '0=30-30',
    '1=100-400',
    '2=400-500,c,500-1000,c,500-1000,c,500-1000,c,500-1000',
    '3=9-9,500-1000',
    '4=500-1000',
    '5=500-1000',
    '6=500-1000',
    '7=500-1000'
  ],
  null,
  2
)

const defaultTlsSettings = () => ({
  server_name: '',
  cert_mode: 'self',
  reject_unknown_sni: '0',
  allow_insecure: '0'
})

const defaultEncryptionSettings = () => ({
  mode: 'native',
  rtt: '0rtt',
  ticket: '600s'
})

const emptyNode = () => ({
  name: '',
  group_id: [],
  route_id: [],
  tags: [],
  parent_id: null,
  host: '',
  listen_ip: '',
  port: '',
  server_port: '',
  protocol: '',
  tls: 0,
  flow: null,
  network: 'tcp',
  encryption: null,
  obfs: null,
  obfs_password: '',
  up_mbps: 0,
  down_mbps: 0,
  rate: 1,
  speed_limit: 0,
  show: 1,
  dialer_proxy_in: 0,
  dialer_proxy_out: 0,
  realm_ip: 0,
  disable_sni: 0,
  zero_rtt_handshake: 0,
  udp_relay_mode: 'native',
  congestion_control: 'cubic',
  sort: 0,
  install_command: ''
})

function pretty(value) {
  if (value === null || value === undefined || value === '') return ''
  try {
    return typeof value === 'string' ? value : JSON.stringify(value, null, 2)
  } catch (e) {
    return ''
  }
}

function protocolColor(protocol) {
  return (protocolMeta[protocol] || { color: '#FF0000' }).color
}

function protocolName(protocol) {
  return (protocolMeta[protocol] || { name: protocol }).name
}

export default function ServerManage() {
  const [rows, setRows] = useState([])
  const [groups, setGroups] = useState([])
  const [routes, setRoutes] = useState([])
  const [loading, setLoading] = useState(false)

  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState(emptyNode())
  const [tlsSettings, setTlsSettings] = useState(defaultTlsSettings())
  const [encryptionSettings, setEncryptionSettings] = useState(defaultEncryptionSettings())
  const [networkText, setNetworkText] = useState('')
  const [paddingText, setPaddingText] = useState('')

  const [child, setChild] = useState({ open: false, type: '', title: '' })

  const [search, setSearch] = useState('')
  const [sortMode, setSortMode] = useState(false)
  const [sortRows, setSortRows] = useState([])
  const [dragIndex, setDragIndex] = useState(null)
  const [dropTarget, setDropTarget] = useState(null)
  const [sortSaving, setSortSaving] = useState(false)

  const load = () => {
    setLoading(true)
    api.get('/server/manage/getNodes').then((res) => {
      setLoading(false)
      if (res.code === 200) setRows(res.data || [])
    })
  }

  useEffect(() => {
    load()
    Promise.all([api.get('/server/group/fetch'), api.get('/server/route/fetch')]).then(([groupRes, routeRes]) => {
      if (groupRes.code === 200) setGroups(groupRes.data || [])
      if (routeRes.code === 200) setRoutes(routeRes.data || [])
    })
  }, [])

  const sorted = useMemo(() => {
    return rows.slice().sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
  }, [rows])

  const displayRows = useMemo(() => {
    if (!search) return sorted
    const keyword = search.toLowerCase()
    return sorted.filter(
      (item) =>
        String(item.name).toLowerCase().includes(keyword) ||
        String(item.host).toLowerCase().includes(keyword) ||
        String(item.id) === keyword
    )
  }, [sorted, search])

  const openCreate = () => {
    setRecord(emptyNode())
    setTlsSettings(defaultTlsSettings())
    setEncryptionSettings(defaultEncryptionSettings())
    setNetworkText('')
    setPaddingText('')
    setOpen(true)
  }

  const openEdit = (row) => {
    setRecord({ ...emptyNode(), ...row })
    setTlsSettings({ ...defaultTlsSettings(), ...(row.tls_settings || {}) })
    setEncryptionSettings({ ...defaultEncryptionSettings(), ...(row.encryption_settings || {}) })
    setNetworkText(pretty(row.network_settings))
    setPaddingText(typeof row.padding_scheme === 'string' ? row.padding_scheme : pretty(row.padding_scheme))
    setOpen(true)
  }

  const parseJson = (text, label) => {
    if (!text || !text.trim()) return null
    try {
      return JSON.parse(text)
    } catch (e) {
      message.error(`${label} 不是合法的 JSON`)
      throw e
    }
  }

  const save = () => {
    if (!record.name || !record.group_id.length || !record.host || !record.port || !record.server_port || !record.protocol) {
      message.warning('请填写节点名称、权限组、连接地址、连接端口、服务端口和节点协议')
      return
    }
    let params
    try {
      params = {
        ...record,
        listen_ip: record.listen_ip || '0.0.0.0',
        sort: Number(record.sort || 0),
        rate: Number(record.rate || 1),
        group_id: record.group_id,
        route_id: record.route_id && record.route_id.length ? record.route_id : null,
        tags: record.tags && record.tags.length ? record.tags : null,
        parent_id: record.parent_id || null,
        tls_settings: tlsSettings,
        network_settings: parseJson(networkText, '传输协议配置'),
        encryption_settings: Object.keys(encryptionSettings).length ? encryptionSettings : null,
        padding_scheme: paddingText && paddingText.trim() ? paddingText : null
      }
    } catch (e) {
      return
    }
    delete params.install_command
    setSaving(true)
    api.post('/server/v2node/save', params).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setOpen(false)
        load()
      }
    })
  }

  const drop = (id) => {
    api.post('/server/v2node/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load()
      }
    })
  }

  const toggleShow = (row, show) => {
    api.post('/server/v2node/update', { id: row.id, show: show ? 1 : 0 }).then((res) => {
      if (res.code === 200) load()
    })
  }

  const copyNode = (id) => {
    api.post('/server/v2node/copy', { id }).then((res) => {
      if (res.code === 200) {
        message.success('复制成功')
        load()
      }
    })
  }

  const enterSort = () => {
    setSortRows(sorted.map((item) => ({ ...item })))
    setSearch('')
    setSortMode(true)
  }

  const saveSort = () => {
    const payload = {}
    sortRows.forEach((item, index) => {
      payload[item.id] = index
    })
    setSortSaving(true)
    api.post('/server/manage/sort', { v2node: payload }).then((res) => {
      setSortSaving(false)
      if (res.code === 200) {
        message.success('排序已保存')
        setSortMode(false)
        load()
      }
    })
  }

  const onDragStart = (index, event) => {
    setDragIndex(index)
    setDropTarget(null)
    const row = event.currentTarget.closest('tr')
    if (row && event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setDragImage(row, 20, 20)
    }
  }

  const onDragOver = (event, index) => {
    event.preventDefault()
    if (dragIndex === null) return
    const rect = event.currentTarget.getBoundingClientRect()
    const position = event.clientY < rect.top + rect.height / 2 ? 'before' : 'after'
    setDropTarget((prev) =>
      prev && prev.index === index && prev.position === position ? prev : { index, position }
    )
  }

  const onDrop = (event, index) => {
    event.preventDefault()
    if (dragIndex === null) {
      setDropTarget(null)
      return
    }
    let position = dropTarget && dropTarget.index === index ? dropTarget.position : 'before'
    const rect = event.currentTarget.getBoundingClientRect()
    if (!dropTarget || dropTarget.index !== index) {
      position = event.clientY < rect.top + rect.height / 2 ? 'before' : 'after'
    }
    let target = index + (position === 'after' ? 1 : 0)
    if (dragIndex < target) target -= 1
    if (target !== dragIndex) {
      const next = sortRows.slice()
      const [moved] = next.splice(dragIndex, 1)
      next.splice(target, 0, moved)
      setSortRows(next)
    }
    setDragIndex(null)
    setDropTarget(null)
  }

  const onDragEnd = () => {
    setDragIndex(null)
    setDropTarget(null)
  }

  const openChild = (type, title) => setChild({ open: true, type, title })
  const closeChild = () => setChild({ open: false, type: '', title: '' })

  const setField = (key, value) => setRecord((prev) => ({ ...prev, [key]: value }))

  const changeProtocol = (protocol) => {
    const next = { ...record, protocol }
    if (['anytls', 'hysteria2', 'tuic'].includes(protocol)) next.tls = 1
    if (protocol === 'shadowsocks' || protocol === 'hysteria2' || protocol === 'tuic') {
      next.network = 'tcp'
    }
    setRecord(next)
  }

  const groupName = (id) => {
    const group = groups.find((item) => item.id === Number(id))
    return group ? group.name : String(id)
  }

  const idTag = (row) => {
    const color = protocolColor(row.protocol || row.type)
    const label = row.parent_id ? `${row.id} => ${row.parent_id}` : row.id
    return (
      <Tag style={{ marginRight: 0, color: '#fff', backgroundColor: color, borderColor: color }}>{label}</Tag>
    )
  }

  const columns = [
    {
      title: '节点ID',
      dataIndex: 'id',
      key: 'id',
      width: 90,
      render: (_, row) => idTag(row)
    },
    {
      title: '显隐',
      dataIndex: 'show',
      key: 'show',
      width: 60,
      render: (v, row) => <Switch size="small" checked={!!Number(v)} onChange={(checked) => toggleShow(row, checked)} />
    },
    {
      title: (
        <Tooltip
          placement="top"
          title={
            <div>
              <Badge status="error" /> 未运行
              <br />
              <Badge status="warning" /> 无人使用或服务端上报异常
              <br />
              <Badge status="processing" /> 运行正常
            </div>
          }
        >
          节点 <QuestionCircleOutlined />
        </Tooltip>
      ),
      dataIndex: 'name',
      key: 'name',
      width: 220,
      render: (v, row) => (
        <span style={{ paddingLeft: row.parent_id ? 20 : 0 }}>
          <span className="v2board-node-status">
            <Badge status={statusMap[row.available_status]} />
          </span>
          <span>{v}</span>
        </span>
      )
    },
    {
      title: '协议',
      dataIndex: 'protocol',
      key: 'protocol',
      width: 120,
      render: (v, row) => protocolName(v || row.type)
    },
    {
      title: '地址',
      dataIndex: 'host',
      key: 'host',
      render: (v, row) => (
        <span style={{ cursor: 'pointer' }} onClick={() => copyText(`${row.host}:${row.port}`)}>
          {row.host}:{row.port}
        </span>
      )
    },
    {
      title: '人数',
      dataIndex: 'online',
      key: 'online',
      width: 70,
      render: (v) => (
        <span>
          <UserOutlined /> {v || 0}
        </span>
      )
    },
    {
      title: '倍率',
      dataIndex: 'rate',
      key: 'rate',
      width: 70,
      render: (v) => `${v || 1} x`
    },
    {
      title: '权限组',
      dataIndex: 'group_id',
      key: 'group_id',
      render: (v) => {
        const ids = Array.isArray(v) ? v : v ? [v] : []
        if (!ids.length) return '-'
        return (
          <span>
            {ids.map((id) => (
              <Tag key={id} style={{ marginBottom: 2 }}>
                {groupName(id)}
              </Tag>
            ))}
          </span>
        )
      }
    },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      fixed: 'right',
      width: 100,
      render: (_, row) => (
        <Dropdown
          trigger={['click']}
          overlay={
            <Menu
              items={[
                {
                  key: 'edit',
                  label: (
                    <span>
                      <EditOutlined /> 编辑
                    </span>
                  ),
                  onClick: () => openEdit(row)
                },
                {
                  key: 'copy',
                  label: (
                    <span>
                      <CopyOutlined /> 复制
                    </span>
                  ),
                  onClick: () => copyNode(row.id)
                },
                {
                  key: 'delete',
                  label: (
                    <span>
                      <DeleteOutlined /> 删除
                    </span>
                  ),
                  danger: true,
                  onClick: () => drop(row.id)
                }
              ]}
            />
          }
        >
          <a onClick={(e) => e.preventDefault()}>
            操作 <CaretDownOutlined />
          </a>
        </Dropdown>
      )
    }
  ]

  const sortColumns = [
    {
      title: '排序',
      key: 'sort_handle',
      width: 100,
      render: (_, row, index) => (
        <span
          className="v2board-drag-handle"
          draggable
          onDragStart={(event) => {
            event.stopPropagation()
            onDragStart(index, event)
          }}
          onDragEnd={onDragEnd}
        >
          <MenuOutlined style={{ fontSize: 16 }} />
        </span>
      )
    },
    {
      title: '节点ID',
      dataIndex: 'id',
      key: 'id',
      width: 150,
      render: (_, row) => idTag(row)
    },
    {
      title: '节点',
      dataIndex: 'name',
      key: 'name',
      render: (v, row) => (
        <span style={{ paddingLeft: row.parent_id ? 20 : 0 }}>
          {row.parent_id ? '↳ ' : ''}
          {v}
        </span>
      )
    },
    {
      title: '协议',
      dataIndex: 'protocol',
      key: 'protocol',
      width: 120,
      render: (v, row) => protocolName(v || row.type)
    }
  ]

  const isShadowsocks = record.protocol === 'shadowsocks'
  const isHysteria2 = record.protocol === 'hysteria2'
  const isTuic = record.protocol === 'tuic'
  const isAnyTLS = record.protocol === 'anytls'
  const isVless = record.protocol === 'vless'
  const showSecurity = !!record.protocol && !isShadowsocks
  const showSecuritySettings = record.tls !== 0 || isHysteria2 || isTuic

  const securityOptions = isVless
    ? [
        { value: 0, label: '无' },
        { value: 1, label: 'TLS' },
        { value: 2, label: 'Reality' }
      ]
    : isAnyTLS
      ? [
          { value: 1, label: 'TLS' },
          { value: 2, label: 'Reality' }
        ]
      : [{ value: 1, label: 'TLS' }]

  const networkOptions = isShadowsocks
    ? [
        { value: 'tcp', label: 'TCP' },
        { value: 'http', label: 'HTTP伪装' }
      ]
    : [
        { value: 'tcp', label: 'TCP' },
        { value: 'ws', label: 'WebSocket' },
        { value: 'grpc', label: 'gRPC' },
        { value: 'httpupgrade', label: 'HTTPUpgrade' },
        { value: 'xhttp', label: 'XHTTP' }
      ]

  const renderTlsSettings = () => {
    const settings = tlsSettings
    const change = (key, value) => setTlsSettings((prev) => ({ ...prev, [key]: value }))
    const tls = Number(record.tls) || 0
    return (
      <div>
        <div className="form-group">
          <label>Server Name(SNI)</label>
          <Input
            value={settings.server_name}
            placeholder={tls === 2 ? 'REALITY必填，与后端保持一致' : ''}
            onChange={(e) => change('server_name', e.target.value)}
          />
        </div>
        {tls === 1 && (
          <div className="form-group">
            <label>证书模式Cert Mode</label>
            <Select
              style={{ width: '100%' }}
              value={settings.cert_mode || 'self'}
              onChange={(value) => change('cert_mode', value)}
              options={[
                { value: 'self', label: '自签名' },
                { value: 'http', label: 'HTTP申请' },
                { value: 'none', label: '无证书(关闭TLS)' }
              ]}
            />
          </div>
        )}
        {tls === 1 && settings.cert_mode !== 'none' && (
          <>
            <div className="form-group">
              <label>证书公钥文件地址Cert File Path</label>
              <Input
                value={settings.cert_file}
                placeholder="留空在/etc/v2node/目录自动生成"
                onChange={(e) => change('cert_file', e.target.value)}
              />
            </div>
            <div className="form-group">
              <label>证书私钥文件地址Key File Path</label>
              <Input
                value={settings.key_file}
                placeholder="留空在/etc/v2node/目录自动生成"
                onChange={(e) => change('key_file', e.target.value)}
              />
            </div>
          </>
        )}
        {tls === 2 && (
          <>
            <div className="form-group">
              <label>Server Address</label>
              <Input
                value={settings.dest}
                placeholder="REALITY目标地址,默认使用SNI"
                onChange={(e) => change('dest', e.target.value)}
              />
            </div>
            <div className="form-group">
              <label>Server Port</label>
              <Input
                value={settings.server_port}
                placeholder="REALITY目标端口,默认为443"
                onChange={(e) => change('server_port', e.target.value)}
              />
            </div>
            <div className="form-group">
              <label>Proxy Protocol</label>
              <Select
                style={{ width: '100%' }}
                value={Number(settings.xver) || 0}
                onChange={(value) => change('xver', value)}
                options={[
                  { value: 0, label: '0' },
                  { value: 1, label: '1' },
                  { value: 2, label: '2' }
                ]}
              />
            </div>
            <div className="form-group">
              <label>Private Key</label>
              <Input value={settings.private_key} placeholder="留空自动生成" onChange={(e) => change('private_key', e.target.value)} />
            </div>
            <div className="form-group">
              <label>Public Key</label>
              <Input value={settings.public_key} placeholder="留空自动生成" onChange={(e) => change('public_key', e.target.value)} />
            </div>
            <div className="form-group">
              <label>ShortId</label>
              <Input value={settings.short_id} placeholder="留空自动生成" onChange={(e) => change('short_id', e.target.value)} />
            </div>
          </>
        )}
        <div className="form-group">
          <label>FingerPrint</label>
          <Select
            style={{ width: '100%' }}
            value={settings.fingerprint}
            placeholder="TLS指纹默认Chrome"
            onChange={(value) => change('fingerprint', value)}
            options={['chrome', 'firefox', 'safari', 'ios', 'android', 'edge', '360', 'qq'].map((value) => ({
              value,
              label: value === 'ios' ? 'IOS' : value === '360' ? '360' : value === 'qq' ? 'QQ' : value.charAt(0).toUpperCase() + value.slice(1)
            }))}
          />
        </div>
        {tls === 1 && (
          <div className="form-group">
            <label>Reject unknown sni</label>
            <div>
              <Switch
                checked={parseInt(settings.reject_unknown_sni) === 1}
                onChange={(checked) => change('reject_unknown_sni', checked ? '1' : '0')}
              />
            </div>
          </div>
        )}
        <div className="form-group">
          <label>Allow Insecure</label>
          <div>
            <Switch
              checked={parseInt(settings.allow_insecure) === 1}
              onChange={(checked) => change('allow_insecure', checked ? '1' : '0')}
            />
          </div>
        </div>
        <div className="form-group">
          <label>ECH (Encrypted Client Hello)</label>
          <Select
            style={{ width: '100%' }}
            value={settings.ech || ''}
            placeholder="选择 ECH 模式"
            onChange={(value) => change('ech', value)}
            options={[
              { value: '', label: '无' },
              { value: 'cloudflare', label: 'Cloudflare' },
              { value: 'custom', label: '自定义 SNI' }
            ]}
          />
        </div>
        {settings.ech === 'cloudflare' && (
          <div
            className="form-group"
            style={{ background: '#f6ffed', padding: '8px 12px', borderRadius: 4, border: '1px solid #b7eb8f' }}
          >
            <span style={{ color: '#52c41a' }}>
              ✓ Cloudflare 托管 ECH，密钥由 Cloudflare 自动管理，客户端从 DNS 自动获取配置，服务端无需配置
            </span>
          </div>
        )}
        {settings.ech === 'custom' && (
          <>
            <div className="form-group">
              <label>ECH Server Name (伪装域名/外层SNI)</label>
              <Input value={settings.ech_server_name || ''} placeholder="必填" onChange={(e) => change('ech_server_name', e.target.value)} />
            </div>
            <div className="form-group">
              <label>ECH Key (服务端私钥)</label>
              <Input value={settings.ech_key || ''} placeholder="留空自动生成" onChange={(e) => change('ech_key', e.target.value)} />
            </div>
            <div className="form-group">
              <label>ECH Config (客户端配置)</label>
              <Input value={settings.ech_config || ''} placeholder="留空自动生成" onChange={(e) => change('ech_config', e.target.value)} />
            </div>
          </>
        )}
      </div>
    )
  }

  const renderEncryptionSettings = () => {
    const settings = encryptionSettings
    const change = (key, value) => setEncryptionSettings((prev) => ({ ...prev, [key]: value }))
    return (
      <div>
        <div className="form-group">
          <label>Mode</label>
          <Select
            style={{ width: '100%' }}
            value={settings.mode}
            onChange={(value) => change('mode', value)}
            options={[
              { value: 'native', label: 'native' },
              { value: 'xorpub', label: 'xorpub' },
              { value: 'random', label: 'random' }
            ]}
          />
        </div>
        <div className="row">
          <div className="form-group col-md-6 col-xs-12">
            <label>RTT</label>
            <Select
              style={{ width: '100%' }}
              value={settings.rtt}
              onChange={(value) => change('rtt', value)}
              options={[
                { value: '0rtt', label: '0rtt' },
                { value: '1rtt', label: '1rtt' }
              ]}
            />
          </div>
          {settings.rtt === '0rtt' && (
            <div className="form-group col-md-6 col-xs-12">
              <label>Ticket time</label>
              <Input value={settings.ticket} placeholder="最长允许时间" onChange={(e) => change('ticket', e.target.value)} />
            </div>
          )}
        </div>
        <div className="form-group">
          <label>Server Padding</label>
          <Input
            value={settings.server_padding}
            placeholder="留空使用默认值100-111-1111.75-0-111.50-0-3333"
            onChange={(e) => change('server_padding', e.target.value)}
          />
        </div>
        <div className="form-group">
          <label>Private Key</label>
          <Input
            value={settings.private_key}
            placeholder="留空自动生成，需抗量子加密请自行替换"
            onChange={(e) => change('private_key', e.target.value)}
          />
        </div>
        <div className="form-group">
          <label>Client Padding</label>
          <Input
            value={settings.client_padding}
            placeholder="留空使用默认值100-111-1111.75-0-111.50-0-3333"
            onChange={(e) => change('client_padding', e.target.value)}
          />
        </div>
        <div className="form-group">
          <label>Password</label>
          <Input
            value={settings.password}
            placeholder="留空自动生成，需抗量子加密请自行替换"
            onChange={(e) => change('password', e.target.value)}
          />
        </div>
      </div>
    )
  }

  const nodeOptions = sorted
    .filter((item) => item.id !== record.id)
    .map((item) => ({ value: item.id, label: item.name }))

  return (
    <div className="block block-bottom">
      <div className="bg-white">
        <div style={{ padding: 15 }}>
          <Button icon={<PlusOutlined />} onClick={openCreate} />
          <Input
            placeholder="输入任意关键字搜索"
            style={{ width: 200, marginLeft: 8 }}
            value={search}
            disabled={sortMode}
            onChange={(e) => setSearch(e.target.value)}
          />
          <Button
            style={{ float: 'right' }}
            type="primary"
            loading={sortMode ? sortSaving : false}
            onClick={sortMode ? saveSort : enterSort}
          >
            {sortMode ? '保存排序' : '编辑排序'}
          </Button>
        </div>

        <DataTable
          dataSource={sortMode ? sortRows : displayRows}
          columns={sortMode ? sortColumns : columns}
          loading={loading}
          pagination={false}
          scroll={{ x: 1200 }}
          rowClassName={(row, index) => {
            if (!sortMode) return row.parent_id ? 'child_node' : ''
            let className = 'v2board-sort-row'
            if (dragIndex === index) className += ' v2board-sort-dragging'
            if (dropTarget && dropTarget.index === index) {
              className += dropTarget.position === 'before' ? ' v2board-drop-before' : ' v2board-drop-after'
            }
            return className
          }}
          onRow={
            sortMode
              ? (record, index) => ({
                  onDragOver: (event) => onDragOver(event, index),
                  onDrop: (event) => onDrop(event, index)
                })
              : undefined
          }
        />
      </div>

      <Drawer
        title={record.id ? '编辑节点' : '新建节点'}
        open={open}
        onClose={() => setOpen(false)}
        width={500}
        footer={
          <div className="text-right">
            <Space>
              <Button onClick={() => setOpen(false)}>取消</Button>
              <Button type="primary" loading={saving} onClick={save}>
                提交
              </Button>
            </Space>
          </div>
        }
      >
        <div className="row">
          <div className="form-group col-8">
            <label>节点名称</label>
            <Input placeholder="请输入节点名称" value={record.name} onChange={(e) => setField('name', e.target.value)} />
          </div>
          <div className="form-group col-4">
            <label>倍率</label>
            <Input addonAfter="x" placeholder="请输入节点倍率" value={record.rate} onChange={(e) => setField('rate', e.target.value)} />
          </div>
        </div>

        <div className="form-group">
          <label>节点限速</label>
          <Input
            addonAfter="Mbps"
            placeholder="0 或留空表示不限速"
            value={record.speed_limit}
            onChange={(e) => setField('speed_limit', e.target.value)}
          />
        </div>

        <div className="form-group">
          <label>节点标签</label>
          <Select
            mode="tags"
            value={record.tags || []}
            style={{ width: '100%' }}
            placeholder="输入后回车添加标签"
            onChange={(value) => setField('tags', value.length ? value : null)}
            options={(record.tags || []).map((tag) => ({ value: tag, label: tag }))}
          />
        </div>

        <div className="form-group">
          <label>
            权限组{' '}
            <a href="javascript:void(0);" onClick={(e) => e.preventDefault()}>
              添加权限组
            </a>
          </label>
          <Select
            mode="multiple"
            value={record.group_id}
            placeholder="请选择权限组"
            style={{ width: '100%' }}
            onChange={(value) => setField('group_id', value)}
            options={groups.map((group) => ({ value: group.id, label: group.name }))}
          />
        </div>

        <div className="row">
          <div className="form-group col-md-6 col-xs-12">
            <label>链式代理入口</label>
            <div>
              <input
                type="checkbox"
                checked={!!parseInt(record.dialer_proxy_in)}
                onChange={(e) => setField('dialer_proxy_in', e.target.checked ? 1 : 0)}
              />{' '}
              允许作为入口
            </div>
          </div>
          <div className="form-group col-md-6 col-xs-12">
            <label>链式代理出口</label>
            <div>
              <input
                type="checkbox"
                checked={!!parseInt(record.dialer_proxy_out)}
                onChange={(e) => setField('dialer_proxy_out', e.target.checked ? 1 : 0)}
              />{' '}
              允许作为出口
            </div>
          </div>
        </div>

        <div className="row">
          <div className="form-group col-md-6 col-xs-12">
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
              <label style={{ margin: 0 }}>连接地址</label>
              <label
                title="勾选后将连接地址解析为节点真实 IP"
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: 3,
                  margin: 0,
                  whiteSpace: 'nowrap',
                  fontSize: 12,
                  fontWeight: 600,
                  color: '#333'
                }}
              >
                <input
                  type="checkbox"
                  style={{ width: 12, height: 12, margin: 0, flex: '0 0 auto' }}
                  checked={!!parseInt(record.realm_ip)}
                  onChange={(e) => setField('realm_ip', e.target.checked ? 1 : 0)}
                />
                真实地址
              </label>
            </div>
            <Input placeholder="地址或IP" value={record.host} onChange={(e) => setField('host', e.target.value)} />
          </div>
          <div className="form-group col-md-6 col-xs-12">
            <label>监听地址</label>
            <Input
              placeholder="地址或IP默认为0.0.0.0"
              value={record.listen_ip}
              onChange={(e) => setField('listen_ip', e.target.value)}
            />
          </div>
        </div>

        <div className="row">
          <div className="form-group col-md-6 col-xs-12">
            <label>连接端口</label>
            <Input placeholder="用户连接端口" value={record.port} onChange={(e) => setField('port', e.target.value)} />
          </div>
          <div className="form-group col-md-6 col-xs-12">
            <label>服务端口</label>
            <Input placeholder="服务端开放端口" value={record.server_port} onChange={(e) => setField('server_port', e.target.value)} />
          </div>
        </div>

        <div className="row">
          <div className="form-group col-md-6 col-xs-12">
            <label>节点协议</label>
            <Select
              value={record.protocol || undefined}
              style={{ width: '100%' }}
              placeholder="选择节点协议"
              onChange={changeProtocol}
              options={protocolOptions.map((protocol) => ({ value: protocol, label: protocolName(protocol) }))}
            />
          </div>
          {showSecurity && (
            <div className="form-group col-md-6 col-xs-12">
              <label>
                安全性{' '}
                {showSecuritySettings && (
                  <a href="javascript:void(0);" onClick={(e) => { e.preventDefault(); openChild('tls_settings', '编辑安全性配置') }}>
                    编辑配置
                  </a>
                )}
              </label>
              <Select
                value={Number(record.tls) || 0}
                style={{ width: '100%' }}
                onChange={(value) => setField('tls', value)}
                options={securityOptions}
              />
            </div>
          )}
        </div>

        {record.protocol && (
          <div className="row">
            <div className="form-group col-md-12 col-xs-12">
              <label>
                传输协议{' '}
                <a href="javascript:void(0);" onClick={(e) => { e.preventDefault(); openChild('network_settings', '编辑协议配置') }}>
                  编辑配置
                </a>
              </label>
              {isHysteria2 || isTuic ? (
                <Select value="udp" disabled style={{ width: '100%' }} options={[{ value: 'udp', label: 'UDP' }]} />
              ) : (
                <Select
                  value={record.network || 'tcp'}
                  placeholder="选择传输协议"
                  style={{ width: '100%' }}
                  onChange={(value) => setField('network', value)}
                  options={networkOptions}
                />
              )}
            </div>
          </div>
        )}

        {isAnyTLS && (
          <div className="row">
            <div className="form-group col-md-12 col-xs-12">
              <label>
                <a href="javascript:void(0);" onClick={(e) => { e.preventDefault(); openChild('padding_scheme', '编辑填充方案') }}>
                  编辑填充方案
                </a>
              </label>
            </div>
          </div>
        )}

        {isHysteria2 && (
          <div className="row">
            <div className="form-group col-md-6 col-xs-12">
              <label>混淆方式obfs</label>
              <Select
                value={record.obfs || ''}
                style={{ width: '100%' }}
                onChange={(value) => setField('obfs', value || null)}
                options={[
                  { value: '', label: '无' },
                  { value: 'salamander', label: 'salamander' }
                ]}
              />
            </div>
            {record.obfs === 'salamander' && (
              <div className="form-group col-md-6 col-xs-12">
                <label>混淆密码obfs_password</label>
                <Input
                  value={record.obfs_password}
                  placeholder="留空自动生成"
                  onChange={(e) => setField('obfs_password', e.target.value)}
                />
              </div>
            )}
          </div>
        )}

        {isHysteria2 && (
          <div className="form-group">
            <label>上行带宽</label>
            <Input
              addonAfter="Mbps"
              placeholder="服务端发送带宽,留空或填0使用BBR"
              value={record.up_mbps}
              onChange={(e) => setField('up_mbps', e.target.value)}
            />
          </div>
        )}

        {isHysteria2 && (
          <div className="form-group">
            <label>下行带宽</label>
            <Input
              addonAfter="Mbps"
              placeholder="服务端接收带宽,留空或填0使用BBR"
              value={record.down_mbps}
              onChange={(e) => setField('down_mbps', e.target.value)}
            />
          </div>
        )}

        {isTuic && (
          <div className="row">
            <div className="form-group col-md-6 col-xs-12">
              <label>禁用SNI</label>
              <Select
                value={parseInt(record.disable_sni) ? 1 : 0}
                style={{ width: '100%' }}
                onChange={(value) => setField('disable_sni', value)}
                options={[
                  { value: 0, label: '否' },
                  { value: 1, label: '是' }
                ]}
              />
            </div>
            <div className="form-group col-md-6 col-xs-12">
              <label>数据包中继模式</label>
              <Select
                value={record.udp_relay_mode || 'native'}
                style={{ width: '100%' }}
                onChange={(value) => setField('udp_relay_mode', value)}
                options={[
                  { value: 'native', label: 'native' },
                  { value: 'quic', label: 'quic' }
                ]}
              />
            </div>
          </div>
        )}

        {isTuic && (
          <div className="row">
            <div className="form-group col-md-6 col-xs-12">
              <label>拥塞控制算法</label>
              <Select
                value={record.congestion_control || 'cubic'}
                style={{ width: '100%' }}
                onChange={(value) => setField('congestion_control', value)}
                options={[
                  { value: 'cubic', label: 'cubic' },
                  { value: 'new_reno', label: 'new_reno' },
                  { value: 'bbr', label: 'bbr' }
                ]}
              />
            </div>
            <div className="form-group col-md-6 col-xs-12">
              <label>客户端启用 0-RTT</label>
              <Select
                value={parseInt(record.zero_rtt_handshake) ? 1 : 0}
                style={{ width: '100%' }}
                onChange={(value) => setField('zero_rtt_handshake', value)}
                options={[
                  { value: 0, label: '否' },
                  { value: 1, label: '是' }
                ]}
              />
            </div>
          </div>
        )}

        {record.protocol && (
          <>
            <div className="row">
              <div className="form-group col-md-12 col-xs-12">
                <label>
                  加密方式{' '}
                  {record.encryption && (
                    <a href="javascript:void(0);" onClick={(e) => { e.preventDefault(); openChild('encryption_settings', '编辑加密配置') }}>
                      编辑配置
                    </a>
                  )}
                </label>
                <Select
                  value={record.encryption || ''}
                  placeholder="选择加密方式"
                  style={{ width: '100%' }}
                  onChange={(value) => setField('encryption', value || null)}
                  options={[
                    { value: '', label: '无' },
                    { value: 'mlkem768x25519plus', label: 'MLKEM768X25519PLUS' }
                  ]}
                />
              </div>
            </div>

            <div className="row">
              <div className="form-group col-md-12 col-xs-12">
                <label>XTLS流控算法</label>
                <Select
                  value={record.flow || ''}
                  placeholder="选择XTLS流控算法"
                  style={{ width: '100%' }}
                  onChange={(value) => setField('flow', value || null)}
                  options={[
                    { value: '', label: '无' },
                    ...(record.network === 'tcp' ? [{ value: 'xtls-rprx-vision', label: 'xtls-rprx-vision' }] : [])
                  ]}
                />
              </div>
            </div>
          </>
        )}

        <div className="form-group">
          <label>
            父节点{' '}
            <a target="_blank" rel="noreferrer" href="https://docs.v2board.com/use/node.html#父节点与子节点关系">
              更多解答
            </a>
          </label>
          <Select
            value={record.parent_id || ''}
            style={{ width: '100%' }}
            onChange={(value) => setField('parent_id', value || null)}
            options={[{ value: '', label: '无' }, ...nodeOptions]}
          />
        </div>

        <div className="form-group">
          <label>路由组</label>
          <Select
            mode="multiple"
            value={record.route_id || []}
            placeholder="请选择路由组"
            style={{ width: '100%' }}
            onChange={(value) => setField('route_id', value.length ? value : null)}
            options={routes.map((route) => ({ value: route.id, label: route.remarks }))}
          />
        </div>

        <div className="form-group">
          <label>一键安装指令</label>
          <TextArea
            value={record.install_command}
            rows={4}
            readOnly
            style={{ backgroundColor: '#f5f5f5a0', cursor: 'text' }}
          />
        </div>
      </Drawer>

      <Drawer
        title={child.title}
        open={child.open}
        onClose={closeChild}
        width={500}
        footer={
          <div className="text-right">
            <Button type="primary" onClick={closeChild}>
              完成
            </Button>
          </div>
        }
      >
        {child.type === 'network_settings' && (
          <div className="form-group">
            <label>
              协议详细配置{' '}
              <a href="https://www.v2ray.com/chapter_02/05_transport.html" target="_blank" rel="noreferrer">
                参考
              </a>
            </label>
            <TextArea
              rows={18}
              value={networkText}
              placeholder={networkPlaceholder[record.network] || ''}
              onChange={(e) => setNetworkText(e.target.value)}
            />
          </div>
        )}
        {child.type === 'tls_settings' && renderTlsSettings()}
        {child.type === 'encryption_settings' && renderEncryptionSettings()}
        {child.type === 'padding_scheme' && (
          <div className="form-group">
            <TextArea
              rows={18}
              value={paddingText}
              placeholder={paddingPlaceholder}
              onChange={(e) => setPaddingText(e.target.value)}
            />
          </div>
        )}
      </Drawer>
    </div>
  )
}
