import React, { useEffect, useState } from 'react'
import { Button, Input, Select, Switch, Space, message, Popconfirm, Checkbox, Tooltip, Row, Col } from 'antd'
import { QuestionCircleOutlined } from '@ant-design/icons'
import { api } from '../api/client'
import { Block, DataTable, DrawerForm } from '../components/ui'
import { periodLabels } from '../utils'

const { TextArea } = Input

const priceFields = ['month_price', 'quarter_price', 'half_year_price', 'year_price', 'two_year_price', 'three_year_price']

const emptyPlan = () => ({
  show: 0,
  renew: 0,
  name: '',
  content: '',
  group_id: undefined,
  transfer_enable: '',
  device_limit: '',
  speed_limit: '',
  capacity_limit: '',
  reset_traffic_method: null,
  month_price: null,
  quarter_price: null,
  half_year_price: null,
  year_price: null,
  two_year_price: null,
  three_year_price: null,
  onetime_price: null,
  reset_price: null,
  force_update: false
})

export default function Plan() {
  const [rows, setRows] = useState([])
  const [groups, setGroups] = useState([])
  const [config, setConfig] = useState({})
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState(emptyPlan())

  const load = () => {
    setLoading(true)
    Promise.all([api.get('/plan/fetch'), api.get('/server/group/fetch'), api.get('/config/fetch', { key: 'site' })]).then(
      ([plans, groupRes, siteRes]) => {
        setLoading(false)
        if (plans.code === 200) setRows(plans.data || [])
        if (groupRes.code === 200) setGroups(groupRes.data || [])
        if (siteRes.code === 200) setConfig((siteRes.data && siteRes.data.site) || {})
      }
    )
  }

  useEffect(load, [])

  const save = () => {
    if (!record.name || record.group_id === undefined || record.transfer_enable === '') {
      message.warning('请填写套餐名称、权限组和流量')
      return
    }
    const params = { ...record }
    if (params.force_update !== true) delete params.force_update
    delete params.count
    setSaving(true)
    api.post('/plan/save', params).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setOpen(false)
        load()
      }
    })
  }

  const drop = (id) => {
    api.post('/plan/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load()
      }
    })
  }

  const update = (id, key, value) => {
    api.post('/plan/update', { id, [key]: value }).then((res) => {
      if (res.code === 200) load()
    })
  }

  const move = (index, offset) => {
    const list = rows.slice()
    const target = index + offset
    if (target < 0 || target >= list.length) return
    ;[list[index], list[target]] = [list[target], list[index]]
    setRows(list)
    api.post('/plan/sort', { plan_ids: list.map((item) => item.id) }).then((res) => {
      if (res.code !== 200) load()
    })
  }

  const priceOnChange = (key, value) => {
    const next = { ...record, [key]: value === '' ? null : value }
    if (value !== '') {
      if (key === 'onetime_price') {
        priceFields.forEach((field) => { next[field] = null })
        next.reset_price = null
      } else if (priceFields.includes(key) || key === 'reset_price') {
        next.onetime_price = null
      }
    }
    setRecord(next)
  }

  const priceCols = [
    ['month_price', '月付'],
    ['quarter_price', '季付'],
    ['half_year_price', '半年付'],
    ['year_price', '年付'],
    ['two_year_price', '两年付'],
    ['three_year_price', '三年付'],
    ['onetime_price', '一次性'],
    ['reset_price', '重置包']
  ]

  const columns = [
    {
      title: '排序',
      key: 'sort',
      width: 70,
      render: (_, row, index) => (
        <Space size={2}>
          <a onClick={() => move(index, -1)}>↑</a>
          <a onClick={() => move(index, 1)}>↓</a>
        </Space>
      )
    },
    {
      title: '销售状态',
      dataIndex: 'show',
      key: 'show',
      render: (v, row) => <Switch size="small" checked={!!Number(v)} onChange={() => update(row.id, 'show', Number(v) ? 0 : 1)} />
    },
    {
      title: (
        <span>
          续费{' '}
          <Tooltip title="在订阅停止销售时，已购用户是否可以续费">
            <QuestionCircleOutlined />
          </Tooltip>
        </span>
      ),
      dataIndex: 'renew',
      key: 'renew',
      render: (v, row) => <Switch size="small" checked={!!Number(v)} onChange={() => update(row.id, 'renew', Number(v) ? 0 : 1)} />
    },
    { title: '名称', dataIndex: 'name', key: 'name' },
    { title: '统计', dataIndex: 'count', key: 'count' },
    { title: '流量', dataIndex: 'transfer_enable', key: 'transfer_enable', render: (v) => `${v} GB` },
    { title: '设备数限制', dataIndex: 'device_limit', key: 'device_limit', render: (v) => (v === null || v === '' ? '-' : v) },
    ...priceCols.map(([key, title]) => ({
      title,
      dataIndex: key,
      key,
      render: (v) => (v === null || v === undefined ? '-' : Number(v).toFixed(2))
    })),
    {
      title: '权限组',
      dataIndex: 'group_id',
      key: 'group_id',
      render: (v) => {
        const group = groups.find((item) => item.id === Number(v))
        return group ? <span className="badge badge-primary">{group.name}</span> : '-'
      }
    },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <Space>
          <a onClick={() => { setRecord({ ...emptyPlan(), ...row, force_update: false }); setOpen(true) }}>编辑</a>
          <Popconfirm title="确定要删除该订阅吗？" onConfirm={() => drop(row.id)} okText="确定" cancelText="取消">
            <a className="text-danger">删除</a>
          </Popconfirm>
        </Space>
      )
    }
  ]

  const currency = config.currency_symbol || '¥'

  return (
    <Block
      title="订阅管理"
      options={<Button type="primary" onClick={() => { setRecord(emptyPlan()); setOpen(true) }}>添加订阅</Button>}
    >
      <DataTable dataSource={rows} columns={columns} loading={loading} pagination={false} />
      <DrawerForm title={record.id ? '编辑订阅' : '新建订阅'} open={open} onClose={() => setOpen(false)} onSubmit={save} submitting={saving} width="80%">
        <div className="form-group">
          <label>套餐名称</label>
          <Input value={record.name} placeholder="请输入套餐名称" onChange={(e) => setRecord({ ...record, name: e.target.value })} />
        </div>
        <div className="form-group">
          <label>套餐描述</label>
          <TextArea rows={4} value={record.content} placeholder="请输入套餐描述，支持HTML" onChange={(e) => setRecord({ ...record, content: e.target.value })} />
        </div>
        <div className="form-group">
          <label>
            售价设置 <span className="text-muted">（金额留空则不会出售；单位为分，一次性与周期价格/重置包互斥）</span>
          </label>
          <Row gutter={16}>
            {priceCols.map(([key, title]) => (
              <Col md={12} key={key}>
                <div className="form-group">
                  <label>{title}</label>
                  <Input
                    addonAfter={currency}
                    value={record[key] !== null && record[key] !== undefined ? record[key] : ''}
                    onChange={(e) => priceOnChange(key, e.target.value)}
                  />
                </div>
              </Col>
            ))}
          </Row>
        </div>
        <div className="form-group">
          <label>套餐流量</label>
          <Input addonAfter="GB" placeholder="请输入套餐流量" value={record.transfer_enable} onChange={(e) => setRecord({ ...record, transfer_enable: e.target.value })} />
        </div>
        <div className="form-group">
          <label>设备数限制</label>
          <Input placeholder="留空则不限制" value={record.device_limit} onChange={(e) => setRecord({ ...record, device_limit: e.target.value })} />
        </div>
        <div className="form-group">
          <label>权限组</label>
          <Select
            style={{ width: '100%' }}
            placeholder="请选择权限组"
            value={record.group_id}
            onChange={(value) => setRecord({ ...record, group_id: value })}
            options={groups.map((group) => ({ value: group.id, label: group.name }))}
          />
        </div>
        <div className="form-group">
          <label>流量重置方式</label>
          <Select
            style={{ width: '100%' }}
            value={record.reset_traffic_method === null ? 'system' : record.reset_traffic_method}
            onChange={(value) => setRecord({ ...record, reset_traffic_method: value === 'system' ? null : value })}
            options={[
              { value: 'system', label: '跟随系统设置' },
              { value: 0, label: '每月1号' },
              { value: 1, label: '按月重置' },
              { value: 2, label: '不重置' },
              { value: 3, label: '每年1月1日' },
              { value: 4, label: '按年重置' }
            ]}
          />
        </div>
        <div className="form-group">
          <label>最大容纳用户量</label>
          <Input placeholder="留空则不限制" value={record.capacity_limit} onChange={(e) => setRecord({ ...record, capacity_limit: e.target.value })} />
        </div>
        <div className="form-group">
          <label>限速</label>
          <Input addonAfter="Mbps" placeholder="留空则不限制" value={record.speed_limit} onChange={(e) => setRecord({ ...record, speed_limit: e.target.value })} />
        </div>
        {record.id ? (
          <div className="form-group">
            <Checkbox checked={!!record.force_update} onChange={(e) => setRecord({ ...record, force_update: e.target.checked })}>
              强制更新到用户（勾选后变更的流量、权限组将应用到该套餐下的用户）
            </Checkbox>
          </div>
        ) : null}
      </DrawerForm>
    </Block>
  )
}
