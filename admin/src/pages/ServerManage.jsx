import React, { useEffect, useMemo, useState } from 'react'
import {
  Button,
  Input,
  InputNumber,
  Select,
  Switch,
  Space,
  message,
  Popconfirm,
  Drawer,
  Row,
  Col,
  Divider,
  Tag
} from 'antd'
import { CopyOutlined, MenuOutlined } from '@ant-design/icons'
import { api } from '../api/client'
import { Block, DataTable, CopyButton, RefreshButton } from '../components/ui'
import { copyText, time } from '../utils'

const { TextArea } = Input

const protocols = ['shadowsocks', 'vless', 'tuic', 'hysteria2', 'anytls']
const networks = ['tcp', 'ws', 'grpc', 'http', 'httpupgrade', 'xhttp']
const tlsOptions = [
  { value: 0, label: '无证书(关闭TLS)' },
  { value: 1, label: '申请证书' },
  { value: 2, label: '自签名' }
]

const emptyNode = () => ({
  name: '',
  group_id: [],
  route_id: [],
  tags: [],
  parent_id: null,
  host: '',
  listen_ip: '0.0.0.0',
  port: '',
  server_port: '',
  protocol: 'vless',
  tls: 1,
  flow: '',
  network: 'tcp',
  cipher: '',
  encryption: '',
  obfs: '',
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
  udp_relay_mode: '',
  congestion_control: '',
  sort: 0
})

function pretty(value) {
  if (value === null || value === undefined || value === '') return ''
  try {
    return JSON.stringify(value, null, 2)
  } catch (e) {
    return ''
  }
}

export default function ServerManage() {
  const [rows, setRows] = useState([])
  const [groups, setGroups] = useState([])
  const [routes, setRoutes] = useState([])
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState(emptyNode())
  const [tlsText, setTlsText] = useState('')
  const [networkText, setNetworkText] = useState('')
  const [encryptionText, setEncryptionText] = useState('')
  const [paddingText, setPaddingText] = useState('')
  const [search, setSearch] = useState('')
  const [sortMode, setSortMode] = useState(false)
  const [sortRows, setSortRows] = useState([])
  const [dragIndex, setDragIndex] = useState(null)
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

  const openEdit = (row) => {
    const next = { ...emptyNode(), ...row }
    next.group_id = row.group_id || []
    next.route_id = row.route_id || []
    next.tags = row.tags || []
    setRecord(next)
    setTlsText(pretty(row.tls_settings))
    setNetworkText(pretty(row.network_settings))
    setEncryptionText(pretty(row.encryption_settings))
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
    if (!record.name || !record.group_id.length || !record.host || !record.port || !record.server_port) {
      message.warning('请填写节点名称、权限组、节点地址、连接端口和服务端口')
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
        tls_settings: parseJson(tlsText, 'TLS 配置'),
        network_settings: parseJson(networkText, '传输协议配置'),
        encryption_settings: parseJson(encryptionText, '加密配置'),
        padding_scheme: paddingText && paddingText.trim() ? paddingText : null
      }
    } catch (e) {
      return
    }
    if (params.tls_settings && params.tls === 1 && params.tls_settings.server_name === undefined) {
      delete params.tls_settings.server_name
    }
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

  const onDragStart = (index) => setDragIndex(index)

  const onDragOver = (event, index) => {
    event.preventDefault()
    if (dragIndex === null || dragIndex === index) return
    const next = sortRows.slice()
    const [moved] = next.splice(dragIndex, 1)
    next.splice(index, 0, moved)
    setSortRows(next)
    setDragIndex(index)
  }

  const onDragEnd = () => setDragIndex(null)

  const sorted = useMemo(() => {
    const list = rows.slice().sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
    return list
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

  const columns = [
    { title: '节点ID', dataIndex: 'id', key: 'id', width: 80 },
    {
      title: '显隐',
      dataIndex: 'show',
      key: 'show',
      width: 70,
      render: (v, row) => <Switch size="small" checked={!!v} onChange={(checked) => toggleShow(row, checked)} />
    },
    {
      title: '节点名称',
      dataIndex: 'name',
      key: 'name',
      render: (v, row) => (
        <span style={{ paddingLeft: row.parent_id ? 20 : 0 }}>
          {row.parent_id ? '↳ ' : ''}
          {v}
          {(row.tags || []).map((tag) => (
            <Tag key={tag} color="blue" className="ml-1">{tag}</Tag>
          ))}
        </span>
      )
    },
    { title: '协议', key: 'protocol', render: (_, row) => `${row.protocol || '-'} / ${row.network || '-'}` },
    {
      title: '地址',
      key: 'host',
      render: (_, row) => (
        <a onClick={() => copyText(`${row.host}:${row.port}`)}>{row.host}:{row.port}</a>
      )
    },
    { title: '人数', dataIndex: 'online', key: 'online', render: (v) => v || 0 },
    { title: '倍率', dataIndex: 'rate', key: 'rate' },
    {
      title: '状态',
      key: 'available_status',
      render: (_, row) => {
        const map = {
          0: ['danger', '未运行'],
          1: ['warning', '无人使用或服务端上报异常'],
          2: ['success', '运行正常']
        }
        const item = map[row.available_status] || ['default', '-']
        return <span className={`badge badge-${item[0]}`}>{item[1]}</span>
      }
    },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      width: 220,
      render: (_, row) => (
        <Space size={4}>
          <a onClick={() => openEdit(row)}>编辑</a>
          <a onClick={() => copyNode(row.id)}>复制</a>
          <Popconfirm title="确定要删除该节点吗？" onConfirm={() => drop(row.id)} okText="删除" cancelText="取消">
            <a className="text-danger">删除</a>
          </Popconfirm>
        </Space>
      )
    }
  ]

  const sortColumns = [
    {
      title: '排序',
      key: 'sort_handle',
      width: 80,
      render: () => <MenuOutlined style={{ cursor: 'move', fontSize: 16 }} />
    },
    {
      title: '节点ID',
      dataIndex: 'id',
      key: 'id',
      width: 120,
      render: (v) => <Tag color="blue">{v}</Tag>
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
      key: 'protocol',
      render: (_, row) => `${row.protocol || '-'} / ${row.network || '-'}`
    }
  ]

  const showNetworkConfig = ['ws', 'grpc', 'http', 'httpupgrade', 'xhttp'].includes(record.network)

  return (
    <Block
      title="节点管理"
      options={
        sortMode ? (
          <Space>
            <span className="text-muted">拖动节点调整顺序</span>
            <Button type="primary" loading={sortSaving} onClick={saveSort}>
              保存排序
            </Button>
          </Space>
        ) : (
          <Space>
            <Input.Search allowClear placeholder="输入任意关键字搜索" style={{ width: 220 }} onChange={(e) => setSearch(e.target.value)} />
            <RefreshButton onClick={load} loading={loading} />
            <Button onClick={enterSort}>编辑排序</Button>
            <Button
              type="primary"
              onClick={() => {
                setRecord(emptyNode())
                setTlsText('')
                setNetworkText('')
                setEncryptionText('')
                setPaddingText('')
                setOpen(true)
              }}
            >
              新建节点
            </Button>
          </Space>
        )
      }
    >
      <DataTable
        dataSource={sortMode ? sortRows : displayRows}
        columns={sortMode ? sortColumns : columns}
        loading={loading}
        pagination={false}
        scroll={{ x: 1100 }}
        rowClassName={(row) => (sortMode ? 'v2board-sort-row' : row.parent_id ? 'child_node' : '')}
        onRow={
          sortMode
            ? (record, index) => ({
                draggable: true,
                onDragStart: () => onDragStart(index),
                onDragOver: (event) => onDragOver(event, index),
                onDragEnd
              })
            : undefined
        }
      />

      <Drawer
        title={record.id ? '编辑节点' : '新建节点'}
        open={open}
        onClose={() => setOpen(false)}
        width={760}
        footer={
          <div className="text-right">
            <Space>
              <Button onClick={() => setOpen(false)}>取消</Button>
              <Button type="primary" loading={saving} onClick={save}>提交</Button>
            </Space>
          </div>
        }
      >
        <h4>基础信息</h4>
        <div className="form-group">
          <label>节点名称</label>
          <Input value={record.name} placeholder="请输入节点名称" onChange={(e) => setRecord({ ...record, name: e.target.value })} />
        </div>
        <div className="form-group">
          <label>权限组</label>
          <Select
            mode="multiple"
            style={{ width: '100%' }}
            value={record.group_id}
            placeholder="请选择权限组"
            onChange={(value) => setRecord({ ...record, group_id: value })}
            options={groups.map((g) => ({ value: g.id, label: g.name }))}
          />
        </div>
        <div className="form-group">
          <label>路由组</label>
          <Select
            mode="multiple"
            style={{ width: '100%' }}
            value={record.route_id}
            placeholder="请选择路由组"
            onChange={(value) => setRecord({ ...record, route_id: value })}
            options={routes.map((r) => ({ value: r.id, label: r.remarks }))}
          />
        </div>
        <div className="form-group">
          <label>节点标签</label>
          <Select
            mode="tags"
            style={{ width: '100%' }}
            value={record.tags}
            placeholder="输入后回车添加标签"
            onChange={(value) => setRecord({ ...record, tags: value })}
          />
        </div>
        <Row gutter={16}>
          <Col md={12}>
            <div className="form-group">
              <label>父节点</label>
              <Select
                allowClear
                style={{ width: '100%' }}
                value={record.parent_id || undefined}
                placeholder="父节点与子节点关系"
                onChange={(value) => setRecord({ ...record, parent_id: value || null })}
                options={sorted.filter((n) => n.id !== record.id).map((n) => ({ value: n.id, label: n.name }))}
              />
            </div>
          </Col>
          <Col md={6}>
            <div className="form-group">
              <label>倍率</label>
              <Input value={record.rate} onChange={(e) => setRecord({ ...record, rate: e.target.value })} />
            </div>
          </Col>
          <Col md={6}>
            <div className="form-group">
              <label>节点限速 (Mbps)</label>
              <Input value={record.speed_limit} onChange={(e) => setRecord({ ...record, speed_limit: e.target.value })} />
            </div>
          </Col>
        </Row>

        <Divider />
        <h4>连接配置</h4>
        <Row gutter={16}>
          <Col md={12}>
            <div className="form-group">
              <label>节点地址</label>
              <Input value={record.host} placeholder="地址或IP" onChange={(e) => setRecord({ ...record, host: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>监听地址</label>
              <Input value={record.listen_ip} placeholder="地址或IP默认为0" onChange={(e) => setRecord({ ...record, listen_ip: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>连接端口</label>
              <Input value={record.port} placeholder="支持端口段 1000-2000" onChange={(e) => setRecord({ ...record, port: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>服务端口</label>
              <Input value={record.server_port} placeholder="服务端开放端口" onChange={(e) => setRecord({ ...record, server_port: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>节点协议</label>
              <Select style={{ width: '100%' }} value={record.protocol} onChange={(value) => setRecord({ ...record, protocol: value })}
                options={protocols.map((p) => ({ value: p, label: p }))} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>传输协议</label>
              <Select style={{ width: '100%' }} value={record.network} onChange={(value) => setRecord({ ...record, network: value })}
                options={networks.map((n) => ({ value: n, label: n }))} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>安全性 / TLS</label>
              <Select style={{ width: '100%' }} value={Number(record.tls)} onChange={(value) => setRecord({ ...record, tls: value })}
                options={tlsOptions} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>加密算法</label>
              <Input value={record.cipher} placeholder="如 aes-128-gcm" onChange={(e) => setRecord({ ...record, cipher: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>流控算法</label>
              <Select
                allowClear
                style={{ width: '100%' }}
                value={record.flow || undefined}
                onChange={(value) => setRecord({ ...record, flow: value || '' })}
                options={[{ value: 'xtls-rprx-vision', label: 'xtls-rprx-vision' }]}
              />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>加密方式</label>
              <Input value={record.encryption} placeholder="如 mlkem768x25519plus" onChange={(e) => setRecord({ ...record, encryption: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>伪装 / obfs</label>
              <Input value={record.obfs} placeholder="salamander 等" onChange={(e) => setRecord({ ...record, obfs: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>混淆密码 obfs_password</label>
              <Input value={record.obfs_password} placeholder="留空自动生成" onChange={(e) => setRecord({ ...record, obfs_password: e.target.value })} />
            </div>
          </Col>
          <Col md={6}>
            <div className="form-group">
              <label>上行带宽</label>
              <Input value={record.up_mbps} onChange={(e) => setRecord({ ...record, up_mbps: e.target.value })} />
            </div>
          </Col>
          <Col md={6}>
            <div className="form-group">
              <label>下行带宽</label>
              <Input value={record.down_mbps} onChange={(e) => setRecord({ ...record, down_mbps: e.target.value })} />
            </div>
          </Col>
        </Row>

        <Divider />
        <h4>TLS 配置（JSON）</h4>
        <TextArea rows={6} value={tlsText} onChange={(e) => setTlsText(e.target.value)}
          placeholder={'{\n  "server_name": "example.com",\n  "allow_insecure": false,\n  "cert_mode": "none",\n  "cert_file": "",\n  "key_file": ""\n}'} />

        {showNetworkConfig ? (
          <>
            <Divider />
            <h4>传输协议配置（JSON）</h4>
            <TextArea rows={6} value={networkText} onChange={(e) => setNetworkText(e.target.value)}
              placeholder={'{\n  "path": "/",\n  "headers": { "Host": "example.com" },\n  "serviceName": "grpc"\n}'} />
          </>
        ) : null}

        <Divider />
        <h4>加密配置（JSON）</h4>
        <TextArea rows={5} value={encryptionText} onChange={(e) => setEncryptionText(e.target.value)}
          placeholder={'{\n  "mode": "native",\n  "rtt": "0rtt"\n}'} />

        <Divider />
        <h4>填充方案（JSON 数组）</h4>
        <TextArea rows={4} value={paddingText} onChange={(e) => setPaddingText(e.target.value)}
          placeholder='[{"type":"padding","value":"1-100"}]' />

        <Divider />
        <h4>其他</h4>
        <Row gutter={16}>
          <Col md={8}>
            <div className="form-group">
              <label>链式代理入口</label>
              <Select style={{ width: '100%' }} value={Number(record.dialer_proxy_in)} onChange={(v) => setRecord({ ...record, dialer_proxy_in: v })}
                options={[{ value: 0, label: '不允许' }, { value: 1, label: '允许作为入口' }]} />
            </div>
          </Col>
          <Col md={8}>
            <div className="form-group">
              <label>链式代理出口</label>
              <Select style={{ width: '100%' }} value={Number(record.dialer_proxy_out)} onChange={(v) => setRecord({ ...record, dialer_proxy_out: v })}
                options={[{ value: 0, label: '不允许' }, { value: 1, label: '允许作为出口' }]} />
            </div>
          </Col>
          <Col md={8}>
            <div className="form-group">
              <label>真实地址</label>
              <Select style={{ width: '100%' }} value={Number(record.realm_ip)} onChange={(v) => setRecord({ ...record, realm_ip: v })}
                options={[{ value: 0, label: '否' }, { value: 1, label: '勾选后将连接地址解析为节点真实 IP' }]} />
            </div>
          </Col>
          <Col md={8}>
            <div className="form-group">
              <label>禁用 SNI</label>
              <Switch checked={Number(record.disable_sni) === 1} onChange={(v) => setRecord({ ...record, disable_sni: v ? 1 : 0 })} />
            </div>
          </Col>
          <Col md={8}>
            <div className="form-group">
              <label>0-RTT</label>
              <Switch checked={Number(record.zero_rtt_handshake) === 1} onChange={(v) => setRecord({ ...record, zero_rtt_handshake: v ? 1 : 0 })} />
            </div>
          </Col>
          <Col md={8}>
            <div className="form-group">
              <label>显示</label>
              <Switch checked={Number(record.show) === 1} onChange={(v) => setRecord({ ...record, show: v ? 1 : 0 })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>数据包中继模式</label>
              <Input value={record.udp_relay_mode} placeholder="native / quic" onChange={(e) => setRecord({ ...record, udp_relay_mode: e.target.value })} />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label>拥塞控制算法</label>
              <Input value={record.congestion_control} placeholder="bbr / cubic" onChange={(e) => setRecord({ ...record, congestion_control: e.target.value })} />
            </div>
          </Col>
        </Row>
        {record.install_command ? (
          <div className="form-group">
            <label>一键安装指令</label>
            <Input.TextArea rows={3} readOnly value={record.install_command} />
            <Button size="small" icon={<CopyOutlined />} className="mt-2" onClick={() => copyText(record.install_command)}>复制</Button>
          </div>
        ) : null}
      </Drawer>
    </Block>
  )
}
