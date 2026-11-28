import React, { useEffect, useState } from 'react'
import {
  Button,
  Input,
  Select,
  Switch,
  Space,
  message,
  Checkbox,
  Tooltip,
  Row,
  Col,
  Drawer,
  Divider,
  Tag,
  Dropdown,
  Menu
} from 'antd'
import {
  PlusOutlined,
  QuestionCircleOutlined,
  InfoCircleOutlined,
  CaretDownOutlined,
  EditOutlined,
  DeleteOutlined,
  UserOutlined,
  MenuOutlined
} from '@ant-design/icons'
import { api } from '../api/client'
import { DataTable } from '../components/ui'

const { TextArea } = Input

const priceFields = ['month_price', 'quarter_price', 'half_year_price', 'year_price', 'two_year_price', 'three_year_price']

const formPrices = [
  ['month_price', '月付'],
  ['quarter_price', '季付'],
  ['half_year_price', '半年'],
  ['year_price', '年付'],
  ['two_year_price', '两年付'],
  ['three_year_price', '三年付']
]

const tablePrices = [
  ['month_price', '月付'],
  ['quarter_price', '季付'],
  ['half_year_price', '半年付'],
  ['year_price', '年付'],
  ['two_year_price', '两年付'],
  ['three_year_price', '三年付'],
  ['onetime_price', '一次性'],
  ['reset_price', '重置包']
]

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

  const [dragIndex, setDragIndex] = useState(null)
  const [dropTarget, setDropTarget] = useState(null)

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

  const saveSort = (list) => {
    api.post('/plan/sort', { plan_ids: list.map((item) => item.id) }).then((res) => {
      if (res.code === 200) load()
      else load()
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
      const next = rows.slice()
      const [moved] = next.splice(dragIndex, 1)
      next.splice(target, 0, moved)
      setRows(next)
      saveSort(next)
    }
    setDragIndex(null)
    setDropTarget(null)
  }

  const onDragEnd = () => {
    setDragIndex(null)
    setDropTarget(null)
  }

  const priceOnChange = (key, value) => {
    const next = { ...record, [key]: value === '' ? null : value }
    if (value !== '') {
      if (key === 'onetime_price') {
        priceFields.forEach((field) => {
          next[field] = null
        })
        next.reset_price = null
      } else if (priceFields.includes(key) || key === 'reset_price') {
        next.onetime_price = null
      }
    }
    setRecord(next)
  }

  const currency = config.currency_symbol || '¥'

  const columns = [
    {
      title: '排序',
      key: 'sort',
      width: 70,
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
      title: '销售状态',
      dataIndex: 'show',
      key: 'show',
      render: (v, row) => (
        <Switch size="small" checked={!!Number(v)} onChange={() => update(row.id, 'show', Number(v) ? 0 : 1)} />
      )
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
      render: (v, row) => (
        <Switch size="small" checked={!!Number(v)} onChange={() => update(row.id, 'renew', Number(v) ? 0 : 1)} />
      )
    },
    { title: '名称', dataIndex: 'name', key: 'name' },
    {
      title: '统计',
      dataIndex: 'count',
      key: 'count',
      render: (v) => (
        <span>
          <UserOutlined /> {v || 0}
        </span>
      )
    },
    { title: '流量', dataIndex: 'transfer_enable', key: 'transfer_enable', render: (v) => `${v} GB` },
    {
      title: '设备',
      dataIndex: 'device_limit',
      key: 'device_limit',
      render: (v) => (v === null || v === '' ? '-' : v)
    },
    ...tablePrices.map(([key, title]) => ({
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
        return group ? <Tag>{group.name}</Tag> : '-'
      }
    },
    {
      title: '操作',
      key: 'action',
      fixed: 'right',
      align: 'right',
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
                  onClick: () => {
                    setRecord({ ...emptyPlan(), ...row, force_update: false })
                    setOpen(true)
                  }
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

  return (
    <div className="block block-rounded">
      <div className="bg-white">
        <div style={{ padding: 15 }}>
          <Button
            icon={<PlusOutlined />}
            onClick={() => {
              setRecord(emptyPlan())
              setOpen(true)
            }}
          >
            添加订阅
          </Button>
        </div>

        <DataTable
          dataSource={rows}
          columns={columns}
          loading={loading}
          pagination={false}
          scroll={{ x: 1300 }}
          rowClassName={(row, index) => {
            let className = 'v2board-sort-row v2board-plan-sort-row'
            if (dragIndex === index) className += ' v2board-sort-dragging'
            if (dropTarget && dropTarget.index === index) {
              className += dropTarget.position === 'before' ? ' v2board-drop-before' : ' v2board-drop-after'
            }
            return className
          }}
          onRow={(record, index) => ({
            onDragOver: (event) => onDragOver(event, index),
            onDrop: (event) => onDrop(event, index)
          })}
        />
      </div>

      <Drawer
        title={record.id ? '编辑订阅' : '新建订阅'}
        open={open}
        onClose={() => setOpen(false)}
        width={500}
        footer={
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <Checkbox checked={!!record.force_update} onChange={(e) => setRecord({ ...record, force_update: e.target.checked })}>
              强制更新到用户
            </Checkbox>
            <Space>
              <Button onClick={() => setOpen(false)}>取消</Button>
              <Button type="primary" loading={saving} onClick={save}>
                提交
              </Button>
            </Space>
          </div>
        }
      >
        <div className="form-group">
          <label htmlFor="example-text-input-alt">套餐名称</label>
          <Input
            placeholder="请输入套餐名称"
            value={record.name}
            onChange={(e) => setRecord({ ...record, name: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">套餐描述</label>
          <TextArea
            rows={4}
            value={record.content}
            placeholder="请输入套餐描述，支持HTML"
            onChange={(e) => setRecord({ ...record, content: e.target.value })}
          />
        </div>

        <Divider orientation="center">
          售价设置{' '}
          <Tooltip placement="top" title="将金额留空则不会进行出售">
            <InfoCircleOutlined />
          </Tooltip>
        </Divider>
        <div className="text-muted font-size-sm mb-3">一次性与周期价格、重置包互斥；周期之间、周期与重置包可共存。</div>

        <Row gutter={10}>
          {formPrices.map(([key, title]) => (
            <Col md={4} key={key}>
              <div className="form-group">
                <label htmlFor="example-text-input-alt">{title}</label>
                <Input
                  value={record[key] !== null && record[key] !== undefined ? record[key] : ''}
                  onChange={(e) => priceOnChange(key, e.target.value)}
                />
              </div>
            </Col>
          ))}
        </Row>

        <Row gutter={10}>
          <Col md={12}>
            <div className="form-group">
              <label htmlFor="example-text-input-alt">一次性</label>
              <Input
                addonAfter={currency}
                value={record.onetime_price !== null && record.onetime_price !== undefined ? record.onetime_price : ''}
                onChange={(e) => priceOnChange('onetime_price', e.target.value)}
              />
            </div>
          </Col>
          <Col md={12}>
            <div className="form-group">
              <label htmlFor="example-text-input-alt">重置包</label>
              <Input
                addonAfter={currency}
                value={record.reset_price !== null && record.reset_price !== undefined ? record.reset_price : ''}
                onChange={(e) => priceOnChange('reset_price', e.target.value)}
              />
            </div>
          </Col>
        </Row>

        <Divider />

        <div className="form-group">
          <label htmlFor="example-text-input-alt">套餐流量</label>
          <Input
            addonAfter="GB"
            placeholder="请输入套餐流量"
            value={record.transfer_enable}
            onChange={(e) => setRecord({ ...record, transfer_enable: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">设备数限制</label>
          <Input
            placeholder="留空则不限制"
            value={record.device_limit}
            onChange={(e) => setRecord({ ...record, device_limit: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">
            权限组{' '}
            <a href="javascript:void(0);" onClick={(e) => e.preventDefault()}>
              添加权限组
            </a>
          </label>
          <Select
            style={{ width: '100%' }}
            placeholder="请选择权限组"
            value={record.group_id}
            onChange={(value) => setRecord({ ...record, group_id: value })}
            options={groups.map((group) => ({ value: group.id, label: group.name }))}
          />
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">流量重置方式</label>
          <Select
            style={{ width: '100%' }}
            placeholder="请选择权限组"
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
          <label htmlFor="example-text-input-alt">最大容纳用户量</label>
          <Input
            placeholder="留空则不限制"
            value={record.capacity_limit}
            onChange={(e) => setRecord({ ...record, capacity_limit: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">限速</label>
          <Input
            addonAfter="Mbps"
            placeholder="留空则不限制"
            value={record.speed_limit}
            onChange={(e) => setRecord({ ...record, speed_limit: e.target.value })}
          />
        </div>
      </Drawer>
    </div>
  )
}
