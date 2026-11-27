import React, { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Button, Input, Modal, Select, Drawer, Space, message, Descriptions, Popconfirm, Dropdown, Menu } from 'antd'
import { PlusOutlined, FilterOutlined } from '@ant-design/icons'
import { api } from '../api/client'
import { Block, DataTable } from '../components/ui'
import { commissionStatus, money, orderStatus, periodLabels, time } from '../utils'

const filterKeys = [
  { key: 'trade_no', title: '订单号', condition: ['模糊', '='] },
  {
    key: 'status',
    title: '订单状态',
    type: 'select',
    condition: ['='],
    options: [
      { label: '未支付', value: 0 },
      { label: '已支付', value: 1 },
      { label: '已取消', value: 2 },
      { label: '已完成', value: 3 },
      { label: '已折抵', value: 4 }
    ]
  },
  {
    key: 'commission_status',
    title: '佣金状态',
    type: 'select',
    condition: ['='],
    options: [
      { label: '待确认', value: 0 },
      { label: '发放中', value: 1 },
      { label: '已发放', value: 2 },
      { label: '无效', value: 3 }
    ]
  },
  { key: 'user_id', title: '用户ID', condition: ['='] },
  { key: 'invite_user_id', title: '邀请人ID', condition: ['=', '!='] },
  { key: 'callback_no', title: '回调单号', condition: ['模糊'] },
  { key: 'commission_balance', title: '佣金金额', condition: ['>', '<', '=', '!=', '>=', '<='] }
]

const typeText = { 1: '新购', 2: '续费', 3: '变更', 4: '流量包', 9: '充值' }

export default function Order() {
  const [searchParams] = useSearchParams()
  const [rows, setRows] = useState([])
  const [plans, setPlans] = useState([])
  const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 })
  const [filters, setFilters] = useState([])
  const [loading, setLoading] = useState(false)

  const [filterOpen, setFilterOpen] = useState(false)
  const [draftFilters, setDraftFilters] = useState([])

  const [addOpen, setAddOpen] = useState(false)
  const [assign, setAssign] = useState({ email: '', plan_id: undefined, period: 'month_price', total_amount: '' })

  const [detailOpen, setDetailOpen] = useState(false)
  const [detail, setDetail] = useState(null)

  const load = (page = pagination, activeFilters = filters) => {
    setLoading(true)
    const params = { current: page.current, pageSize: page.pageSize }
    if (activeFilters.length) params.filter = activeFilters
    api.get('/order/fetch', params).then((res) => {
      setLoading(false)
      if (res.code === 200) {
        setRows(res.data || [])
        setPagination({ ...page, total: res.total || 0 })
      }
    })
  }

  useEffect(() => {
    const presetUserId = searchParams.get('user_id')
    const initial = presetUserId
      ? [{ key: 'user_id', condition: '=', value: presetUserId }]
      : []
    setFilters(initial)
    setDraftFilters(initial)
    load({ current: 1, pageSize: 10 }, initial)
    api.get('/plan/fetch').then((res) => {
      if (res.code === 200) setPlans(res.data || [])
    })
  }, [])

  const applyFilter = () => {
    const valid = draftFilters.filter((item) => item.key && item.condition && item.value !== '')
    setFilters(valid)
    setFilterOpen(false)
    setPagination({ ...pagination, current: 1 })
    load({ ...pagination, current: 1 }, valid)
  }

  const paid = (tradeNo) => {
    api.post('/order/paid', { trade_no: tradeNo }).then((res) => {
      if (res.code === 200) {
        message.success('操作成功')
        load(pagination)
      }
    })
  }

  const cancel = (tradeNo) => {
    api.post('/order/cancel', { trade_no: tradeNo }).then((res) => {
      if (res.code === 200) {
        message.success('操作成功')
        load(pagination)
      }
    })
  }

  const updateCommission = (tradeNo, value) => {
    api.post('/order/update', { trade_no: tradeNo, commission_status: value }).then((res) => {
      if (res.code === 200) {
        message.success('操作成功')
        load(pagination)
      }
    })
  }

  const openDetail = (id) => {
    api.post('/order/detail', { id }).then((res) => {
      if (res.code === 200) {
        setDetail(res.data)
        setDetailOpen(true)
      }
    })
  }

  const submitAssign = () => {
    if (!assign.email || !assign.plan_id || !assign.total_amount) {
      message.warning('请填写完整信息')
      return
    }
    api.post('/order/assign', assign).then((res) => {
      if (res.code === 200) {
        message.success('添加成功')
        setAddOpen(false)
        load(pagination)
      }
    })
  }

  const columns = [
    {
      title: '# 订单号',
      dataIndex: 'trade_no',
      key: 'trade_no',
      render: (v, row) => (
        <a onClick={() => openDetail(row.id)}>
          {String(v).substr(0, 3)}...{String(v).substr(-3)}
        </a>
      )
    },
    { title: '类型', dataIndex: 'type', key: 'type', render: (v) => typeText[v] || v },
    { title: '订阅计划', dataIndex: 'plan_name', key: 'plan_name', render: (v) => v || '-' },
    { title: '支付接口', dataIndex: 'payment_name', key: 'payment_name', render: (v) => v || '-' },
    { title: '周期', dataIndex: 'period', key: 'period', render: (v) => <span className="badge badge-light">{periodLabels[v] || v}</span> },
    { title: '支付金额', dataIndex: 'total_amount', key: 'total_amount', align: 'right', render: (v) => (v / 100).toFixed(2) },
    {
      title: '订单状态',
      dataIndex: 'status',
      key: 'status',
      render: (v, row) => {
        const status = orderStatus[v] || { text: v, color: 'default' }
        if (v !== 0 && v !== 3) return <span className={`badge badge-${status.color}`}>{status.text}</span>
        return (
          <Dropdown
            trigger={['click']}
            overlay={
              <Menu
                items={[
                  ...(v === 0 ? [{ key: 'paid', label: '已支付', onClick: () => paid(row.trade_no) }] : []),
                  { key: 'cancel', label: '取消', onClick: () => cancel(row.trade_no) }
                ]}
              />
            }
          >
            <a>
              <span className={`badge badge-${status.color} mr-1`} />
              {v === 0 ? '标记为' : '操作'} ▾
            </a>
          </Dropdown>
        )
      }
    },
    {
      title: '佣金金额',
      dataIndex: 'commission_balance',
      key: 'commission_balance',
      align: 'right',
      render: (v, row) => (row.status === 0 || row.status === 2 ? '-' : v ? (v / 100).toFixed(2) : '-')
    },
    {
      title: '佣金状态',
      dataIndex: 'commission_status',
      key: 'commission_status',
      render: (v, row) => {
        if (row.status === 0 || row.status === 2 || !row.commission_balance) return '-'
        if (v === 2) return <span className="badge badge-success">{commissionStatus[v].text}</span>
        return (
          <Dropdown
            trigger={['click']}
            overlay={
              <Menu
                items={[
                  { key: '0', label: '待确认', disabled: v === 0, onClick: () => updateCommission(row.trade_no, 0) },
                  { key: '1', label: '有效', disabled: v === 1, onClick: () => updateCommission(row.trade_no, 1) },
                  { key: '3', label: '无效', disabled: v === 3, onClick: () => updateCommission(row.trade_no, 3) }
                ]}
              />
            }
          >
            <a>
              <span className={`badge badge-${(commissionStatus[v] || {}).color || 'default'} mr-1`} />
              {commissionStatus[v] ? commissionStatus[v].text : '-'} ▾
            </a>
          </Dropdown>
        )
      }
    },
    { title: '创建时间', dataIndex: 'created_at', key: 'created_at', align: 'right', render: (v) => time(v, 'YYYY/MM/DD HH:mm') }
  ]

  return (
    <Block
      title="订单管理"
      options={
        <Space>
          <Button type={filters.length ? 'primary' : 'default'} icon={<FilterOutlined />} onClick={() => { setDraftFilters(filters.length ? filters : []); setFilterOpen(true) }}>
            过滤器
          </Button>
          <Button icon={<PlusOutlined />} onClick={() => setAddOpen(true)}>
            添加订单
          </Button>
        </Space>
      }
    >
      <DataTable
        dataSource={rows}
        columns={columns}
        loading={loading}
        pagination={{
          ...pagination,
          showSizeChanger: true,
          showTotal: (total) => `共 ${total} 条`,
          onChange: (current, pageSize) => load({ current, pageSize })
        }}
      />

      <Drawer title="过滤器" open={filterOpen} onClose={() => setFilterOpen(false)} width={420}
        footer={
          <div className="text-right">
            <Space>
              <Button onClick={() => setDraftFilters([])}>清空</Button>
              <Button type="primary" onClick={applyFilter}>应用</Button>
            </Space>
          </div>
        }
      >
        {draftFilters.map((item, index) => {
          const meta = filterKeys.find((k) => k.key === item.key) || filterKeys[0]
          return (
            <div key={index} className="p-2 mb-3 border rounded">
              <Select
                style={{ width: '48%', marginRight: '4%' }}
                value={item.key}
                onChange={(value) => {
                  const meta2 = filterKeys.find((k) => k.key === value)
                  const next = draftFilters.slice()
                  next[index] = { key: value, condition: meta2.condition[0], value: '' }
                  setDraftFilters(next)
                }}
                options={filterKeys.map((k) => ({ value: k.key, label: k.title }))}
              />
              <Select
                style={{ width: '48%' }}
                value={item.condition}
                onChange={(value) => {
                  const next = draftFilters.slice()
                  next[index] = { ...item, condition: value }
                  setDraftFilters(next)
                }}
                options={meta.condition.map((c) => ({ value: c, label: c }))}
              />
              {meta.type === 'select' ? (
                <Select
                  style={{ width: '100%', marginTop: 8 }}
                  value={item.value === '' ? undefined : item.value}
                  placeholder="请选择值"
                  onChange={(value) => {
                    const next = draftFilters.slice()
                    next[index] = { ...item, value }
                    setDraftFilters(next)
                  }}
                  options={meta.options}
                />
              ) : (
                <Input
                  style={{ marginTop: 8 }}
                  value={item.value}
                  placeholder="请输入值"
                  onChange={(e) => {
                    const next = draftFilters.slice()
                    next[index] = { ...item, value: e.target.value }
                    setDraftFilters(next)
                  }}
                />
              )}
              <div className="text-right mt-2">
                <Button size="small" danger type="link" onClick={() => setDraftFilters(draftFilters.filter((_, i) => i !== index))}>
                  移除
                </Button>
              </div>
            </div>
          )
        })}
        <Button block type="dashed" onClick={() => setDraftFilters([...draftFilters, { key: 'trade_no', condition: '模糊', value: '' }])}>
          添加过滤条件
        </Button>
      </Drawer>

      <Modal title="添加订单" open={addOpen} onCancel={() => setAddOpen(false)} onOk={submitAssign} okText="提交" cancelText="取消">
        <div className="form-group">
          <label>用户邮箱</label>
          <Input value={assign.email} placeholder="请输入用户邮箱" onChange={(e) => setAssign({ ...assign, email: e.target.value })} />
        </div>
        <div className="form-group">
          <label>订阅计划</label>
          <Select
            style={{ width: '100%' }}
            value={assign.plan_id}
            placeholder="请选择订阅"
            onChange={(value) => setAssign({ ...assign, plan_id: value })}
            options={plans.map((plan) => ({ value: plan.id, label: plan.name }))}
          />
        </div>
        <div className="form-group">
          <label>周期</label>
          <Select
            style={{ width: '100%' }}
            value={assign.period}
            onChange={(value) => setAssign({ ...assign, period: value })}
            options={Object.keys(periodLabels).map((key) => ({ value: key, label: periodLabels[key] }))}
          />
        </div>
        <div className="form-group">
          <label>支付金额（分）</label>
          <Input value={assign.total_amount} onChange={(e) => setAssign({ ...assign, total_amount: e.target.value })} />
        </div>
      </Modal>

      <Modal title="订单信息" open={detailOpen} onCancel={() => setDetailOpen(false)} footer={null} width={720}>
        {detail ? (
          <Descriptions column={1} size="small" bordered>
            <Descriptions.Item label="订单号">{detail.trade_no}</Descriptions.Item>
            <Descriptions.Item label="类型">{typeText[detail.type] || detail.type}</Descriptions.Item>
            <Descriptions.Item label="订阅计划">{detail.plan_name || detail.plan_id || '-'}</Descriptions.Item>
            <Descriptions.Item label="周期">{periodLabels[detail.period] || detail.period}</Descriptions.Item>
            <Descriptions.Item label="订单状态">{orderStatus[detail.status] ? orderStatus[detail.status].text : detail.status}</Descriptions.Item>
            <Descriptions.Item label="支付金额">{money(detail.total_amount)}</Descriptions.Item>
            <Descriptions.Item label="优惠金额">{money(detail.discount_amount)}</Descriptions.Item>
            <Descriptions.Item label="退回金额">{money(detail.refund_amount)}</Descriptions.Item>
            <Descriptions.Item label="余额支付">{money(detail.balance_amount)}</Descriptions.Item>
            <Descriptions.Item label="折抵金额">{money(detail.surplus_amount)}</Descriptions.Item>
            <Descriptions.Item label="支付接口">{detail.payment_id || '-'}</Descriptions.Item>
            <Descriptions.Item label="创建时间">{time(detail.created_at)}</Descriptions.Item>
            <Descriptions.Item label="更新时间">{time(detail.updated_at)}</Descriptions.Item>
          </Descriptions>
        ) : null}
      </Modal>
    </Block>
  )
}
