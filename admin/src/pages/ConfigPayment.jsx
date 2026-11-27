import React, { useEffect, useState } from 'react'
import { Button, Input, Modal, Select, Switch, Space, message, Popconfirm } from 'antd'
import { api } from '../api/client'
import { Block, DataTable } from '../components/ui'

const emptyRecord = () => ({
  name: '',
  icon: '',
  notify_domain: '',
  handling_fee_percent: '',
  handling_fee_fixed: '',
  payment: '',
  config: {}
})

export default function ConfigPayment() {
  const [rows, setRows] = useState([])
  const [methods, setMethods] = useState([])
  const [form, setForm] = useState({})
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState(emptyRecord())

  const load = () => {
    setLoading(true)
    api.get('/payment/fetch').then((res) => {
      setLoading(false)
      if (res.code === 200) setRows(res.data || [])
    })
  }

  useEffect(load, [])

  const loadForm = (payment, id) => {
    api.post('/payment/getPaymentForm', { payment, id }).then((res) => {
      if (res.code === 200) setForm(res.data || {})
    })
  }

  const openEdit = (row) => {
    setRecord({ ...emptyRecord(), ...row, config: row.config || {} })
    setOpen(true)
    api.get('/payment/getPaymentMethods').then((res) => {
      const list = res.code === 200 ? res.data || [] : []
      setMethods(list)
      const payment = row.payment || list[0]
      setRecord((prev) => ({ ...prev, payment }))
      if (payment) loadForm(payment, row.id)
    })
  }

  const save = () => {
    if (!record.name || !record.payment || !record.config) {
      message.warning('请填写显示名称和支付接口')
      return
    }
    const config = {}
    Object.keys(form).forEach((key) => {
      if (form[key].value !== undefined && form[key].value !== null) config[key] = form[key].value
    })
    setSaving(true)
    api.post('/payment/save', { ...record, config }).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setOpen(false)
        load()
      }
    })
  }

  const drop = (id) => {
    api.post('/payment/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load()
      }
    })
  }

  const toggle = (id) => {
    api.post('/payment/show', { id }).then((res) => {
      if (res.code === 200) load()
    })
  }

  const columns = [
    { title: '启用', dataIndex: 'enable', key: 'enable', render: (v, row) => <Switch size="small" checked={!!v} onChange={() => toggle(row.id)} /> },
    { title: '显示名称', dataIndex: 'name', key: 'name' },
    { title: '支付接口', dataIndex: 'payment', key: 'payment' },
    { title: '通知地址', dataIndex: 'notify_url', key: 'notify_url', ellipsis: true },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <Space>
          <a onClick={() => openEdit(row)}>编辑</a>
          <Popconfirm title="确定要删除该条项目吗？" onConfirm={() => drop(row.id)} okText="确定" cancelText="取消">
            <a className="text-danger">删除</a>
          </Popconfirm>
        </Space>
      )
    }
  ]

  return (
    <Block
      title="支付配置"
      options={<Button type="primary" onClick={() => openEdit(emptyRecord())}>添加支付方式</Button>}
    >
      <DataTable dataSource={rows} columns={columns} loading={loading} pagination={false} />
      <Modal
        title={record.id ? '编辑支付方式' : '添加支付方式'}
        open={open}
        onCancel={() => setOpen(false)}
        onOk={save}
        confirmLoading={saving}
        okText={record.id ? '保存' : '添加'}
        cancelText="取消"
        width={720}
      >
        <div className="form-group">
          <label>显示名称</label>
          <Input value={record.name} placeholder="用于前端显示使用" onChange={(e) => setRecord({ ...record, name: e.target.value })} />
        </div>
        <div className="form-group">
          <label>图标URL(选填)</label>
          <Input value={record.icon} placeholder="用于前端显示使用(https://x.com/icon.svg)" onChange={(e) => setRecord({ ...record, icon: e.target.value })} />
        </div>
        <div className="form-group">
          <label>自定义通知域名(选填)</label>
          <Input
            value={record.notify_domain}
            placeholder="网关的通知将会发送到该域名(https://x.com)"
            onChange={(e) => setRecord({ ...record, notify_domain: e.target.value })}
          />
        </div>
        <div className="row">
          <div className="col-6">
            <div className="form-group">
              <label>百分比手续费(选填)</label>
              <Input
                addonAfter="%"
                value={record.handling_fee_percent}
                placeholder="在订单金额基础上附加手续费"
                onChange={(e) => setRecord({ ...record, handling_fee_percent: e.target.value })}
              />
            </div>
          </div>
          <div className="col-6">
            <div className="form-group">
              <label>固定手续费(选填)</label>
              <Input
                addonAfter="分"
                value={record.handling_fee_fixed}
                placeholder="在订单金额基础上附加手续费"
                onChange={(e) => setRecord({ ...record, handling_fee_fixed: e.target.value })}
              />
            </div>
          </div>
        </div>
        <div className="form-group">
          <label>接口文件</label>
          <Select
            style={{ width: '100%' }}
            value={record.payment || undefined}
            placeholder="请选择支付接口"
            onChange={(value) => {
              setRecord({ ...record, payment: value })
              loadForm(value, record.id)
            }}
            options={methods.map((m) => ({ value: m, label: m }))}
          />
        </div>
        {Object.keys(form).length ? (
          <div className="mt-3">
            <div className="text-muted mb-2">网关参数</div>
            {Object.entries(form).map(([key, field]) => (
              <div className="form-group" key={key}>
                <label>{field.label || key}</label>
                <Input
                  value={field.value === undefined || field.value === null ? '' : field.value}
                  placeholder={field.description || ''}
                  onChange={(e) => {
                    setForm({ ...form, [key]: { ...field, value: e.target.value } })
                    setRecord({ ...record, config: { ...(record.config || {}), [key]: e.target.value } })
                  }}
                />
                {field.description ? <div className="text-muted font-size-sm mt-1">{field.description}</div> : null}
              </div>
            ))}
          </div>
        ) : null}
      </Modal>
    </Block>
  )
}
