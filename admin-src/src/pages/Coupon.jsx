import React, { useEffect, useState } from 'react'
import { Button, Input, InputNumber, Modal, Select, Switch, DatePicker, message, Popconfirm } from 'antd'
import dayjs from 'dayjs'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { copyText, download, periodLabels } from '../utils'

const { RangePicker } = DatePicker

const emptyRecord = () => ({
  name: '',
  code: '',
  type: 1,
  value: '',
  started_at: null,
  ended_at: null,
  limit_use: '',
  limit_use_with_user: '',
  limit_plan_ids: null,
  limit_period: null,
  generate_count: ''
})

export default function Coupon() {
  const [rows, setRows] = useState([])
  const [plans, setPlans] = useState([])
  const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 })
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState(emptyRecord())

  const load = (page = pagination) => {
    setLoading(true)
    api.get('/coupon/fetch', { current: page.current, pageSize: page.pageSize }).then((res) => {
      setLoading(false)
      if (res.code === 200) {
        setRows(res.data || [])
        setPagination({ ...page, total: res.total || 0 })
      }
    })
  }

  useEffect(() => {
    load()
    api.get('/plan/fetch').then((res) => {
      if (res.code === 200) setPlans(res.data || [])
    })
  }, [])

  const save = () => {
    if (!record.name || !record.started_at || !record.ended_at) {
      message.warning('请填写名称和有效期')
      return
    }
    const params = { ...record }
    if (params.generate_count === '') delete params.generate_count
    if (!params.code) delete params.code
    setSaving(true)
    api.post('/coupon/generate', params).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        if (res.text) download('coupons.csv', res.text)
        message.success('保存成功')
        setOpen(false)
        load(pagination)
      }
    })
  }

  const drop = (id) => {
    api.post('/coupon/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load(pagination)
      }
    })
  }

  const toggle = (id) => {
    api.post('/coupon/show', { id }).then((res) => {
      if (res.code === 200) load(pagination)
    })
  }

  const columns = [
    { title: '#', dataIndex: 'id', key: 'id' },
    { title: '启用', dataIndex: 'show', key: 'show', render: (v, row) => <Switch size="small" checked={!!v} onChange={() => toggle(row.id)} /> },
    { title: '券名称', dataIndex: 'name', key: 'name' },
    { title: '类型', dataIndex: 'type', key: 'type', render: (v) => (v === 1 ? '金额' : '比例') },
    {
      title: '金额/比例',
      key: 'value',
      render: (_, row) => (row.type === 1 ? `¥${(Number(row.value) / 100).toFixed(2)}` : `${row.value}%`)
    },
    { title: '券码', dataIndex: 'code', key: 'code', render: (v) => <a onClick={() => copyText(v)}>{v}</a> },
    { title: '剩余次数', dataIndex: 'limit_use', key: 'limit_use', render: (v) => (v === null || v === undefined ? '无限' : v) },
    {
      title: '有效期',
      key: 'started_at',
      render: (_, row) => `${dayjs(row.started_at * 1000).format('YYYY/MM/DD HH:mm')} ~ ${dayjs(row.ended_at * 1000).format('YYYY/MM/DD HH:mm')}`
    },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <span>
          <a onClick={() => { setRecord({ ...emptyRecord(), ...row }); setOpen(true) }}>编辑</a>
          <span className="mx-2 text-muted">|</span>
          <Popconfirm title="确定要删除该条项目吗？" onConfirm={() => drop(row.id)} okText="确定" cancelText="取消">
            <a className="text-danger">删除</a>
          </Popconfirm>
        </span>
      )
    }
  ]

  return (
    <TableBlock
      toolbar={<Button onClick={() => { setRecord(emptyRecord()); setOpen(true) }}>添加优惠券</Button>}
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
      <Modal
        title={record.id ? '编辑优惠券' : '新建优惠券'}
        open={open}
        onCancel={() => setOpen(false)}
        onOk={save}
        confirmLoading={saving}
        okText="提交"
        cancelText="取消"
        width={680}
      >
        <div className="form-group">
          <label>名称</label>
          <Input value={record.name} placeholder="请输入优惠券名称" onChange={(e) => setRecord({ ...record, name: e.target.value })} />
        </div>
        {!record.generate_count && (
          <div className="form-group">
            <label>自定义优惠券码</label>
            <Input value={record.code} placeholder="自定义优惠券码(留空随机生成)" onChange={(e) => setRecord({ ...record, code: e.target.value })} />
          </div>
        )}
        <div className="form-group">
          <label>优惠信息</label>
          <Input
            type="number"
            addonBefore={
              <Select value={record.type} style={{ width: 120 }} onChange={(value) => setRecord({ ...record, type: value })}>
                <Select.Option value={1}>按金额优惠</Select.Option>
                <Select.Option value={2}>按比例优惠</Select.Option>
              </Select>
            }
            addonAfter={record.type === 1 ? '分' : '%'}
            placeholder="请输入值"
            value={record.value}
            onChange={(e) => setRecord({ ...record, value: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label>优惠券有效期</label>
          <RangePicker
            style={{ width: '100%' }}
            showTime={{ format: 'HH:mm' }}
            format="YYYY-MM-DD HH:mm"
            placeholder={['开始时间', '结束时间']}
            value={[
              record.started_at ? dayjs(record.started_at * 1000) : null,
              record.ended_at ? dayjs(record.ended_at * 1000) : null
            ]}
            onChange={(values) =>
              setRecord({
                ...record,
                started_at: values && values[0] ? values[0].unix() : null,
                ended_at: values && values[1] ? values[1].unix() : null
              })
            }
          />
        </div>
        <div className="form-group">
          <label>最大使用次数</label>
          <Input
            value={record.limit_use}
            placeholder="限制最大使用次数，用完则无法使用(为空则不限制)"
            onChange={(e) => setRecord({ ...record, limit_use: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label>每个用户可使用次数</label>
          <Input
            value={record.limit_use_with_user}
            placeholder="限制每个用户可使用次数(为空则不限制)"
            onChange={(e) => setRecord({ ...record, limit_use_with_user: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label>指定订阅</label>
          <Select
            mode="multiple"
            style={{ width: '100%' }}
            value={record.limit_plan_ids || []}
            placeholder="限制指定订阅可以使用优惠(为空则不限制)"
            onChange={(value) => setRecord({ ...record, limit_plan_ids: value.length ? value : null })}
          >
            {plans.map((plan) => (
              <Select.Option key={plan.id} value={String(plan.id)}>{plan.name}</Select.Option>
            ))}
          </Select>
        </div>
        <div className="form-group">
          <label>指定周期</label>
          <Select
            mode="multiple"
            style={{ width: '100%' }}
            value={record.limit_period || []}
            placeholder="限制指定周期可以使用优惠(为空则不限制)"
            onChange={(value) => setRecord({ ...record, limit_period: value.length ? value : null })}
            options={Object.keys(periodLabels).map((key) => ({ value: key, label: periodLabels[key] }))}
          />
        </div>
        {!record.code && !record.id && (
          <div className="form-group">
            <label>生成数量</label>
            <Input
              value={record.generate_count}
              placeholder="输入数量批量生成"
              onChange={(e) => setRecord({ ...record, generate_count: e.target.value, code: undefined })}
            />
          </div>
        )}
      </Modal>
    </TableBlock>
  )
}
