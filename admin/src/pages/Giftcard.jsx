import React, { useEffect, useState } from 'react'
import { Button, Input, Modal, Select, DatePicker, message, Popconfirm } from 'antd'
import dayjs from 'dayjs'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { copyText, download } from '../utils'

const { RangePicker } = DatePicker

const typeText = { 1: '金额', 2: '时长', 3: '流量', 4: '重置', 5: '套餐' }

const emptyRecord = () => ({
  name: '',
  code: '',
  type: 1,
  value: '',
  plan_id: null,
  started_at: null,
  ended_at: null,
  limit_use: '',
  generate_count: ''
})

export default function Giftcard() {
  const [rows, setRows] = useState([])
  const [plans, setPlans] = useState([])
  const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 })
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState(emptyRecord())

  const load = (page = pagination) => {
    setLoading(true)
    api.get('/giftcard/fetch', { current: page.current, pageSize: page.pageSize }).then((res) => {
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
    if (record.type !== 4 && (record.value === '' || record.value === null)) {
      message.warning('请填写数值')
      return
    }
    if (record.type === 5 && !record.plan_id) {
      message.warning('请选择订阅')
      return
    }
    const params = { ...record }
    if (params.generate_count === '') delete params.generate_count
    if (!params.code) delete params.code
    setSaving(true)
    api.post('/giftcard/generate', params).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        if (res.text) download('giftcards.csv', res.text)
        message.success('保存成功')
        setOpen(false)
        load(pagination)
      }
    })
  }

  const drop = (id) => {
    api.post('/giftcard/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load(pagination)
      }
    })
  }

  const valueText = (row) => {
    const value = Number(row.value || 0)
    if (row.type === 1) return `¥${(value / 100).toFixed(2)}`
    if (row.type === 2 || row.type === 5) return `${value} 天`
    if (row.type === 3) return `${value} GB`
    return '-'
  }

  const columns = [
    { title: '#', dataIndex: 'id', key: 'id' },
    { title: '名称', dataIndex: 'name', key: 'name' },
    { title: '类型', dataIndex: 'type', key: 'type', render: (v) => typeText[v] || v },
    { title: '数值', key: 'value', render: (_, row) => valueText(row) },
    { title: '卡密', dataIndex: 'code', key: 'code', render: (v) => <a onClick={() => copyText(v)}>{v}</a> },
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
      toolbar={<Button onClick={() => { setRecord(emptyRecord()); setOpen(true) }}>添加礼品卡</Button>}
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
        title={record.id ? '编辑礼品卡' : '新建礼品卡'}
        open={open}
        onCancel={() => setOpen(false)}
        onOk={save}
        confirmLoading={saving}
        okText="提交"
        cancelText="取消"
        width={640}
      >
        <div className="form-group">
          <label>名称</label>
          <Input value={record.name} placeholder="请输入礼品卡名称" onChange={(e) => setRecord({ ...record, name: e.target.value })} />
        </div>
        {!record.generate_count && (
          <div className="form-group">
            <label>自定义礼品卡卡密</label>
            <Input value={record.code} placeholder="自定义礼品卡卡密(留空随机生成)" onChange={(e) => setRecord({ ...record, code: e.target.value })} />
          </div>
        )}
        <div className="form-group">
          <label>礼品卡类型</label>
          <Select
            style={{ width: '100%' }}
            value={record.type}
            onChange={(value) => setRecord({ ...record, type: value })}
            options={[
              { value: 1, label: '增加账户余额' },
              { value: 2, label: '增加订阅时长' },
              { value: 3, label: '增加套餐流量' },
              { value: 4, label: '重置套餐流量' },
              { value: 5, label: '兑换订阅套餐' }
            ]}
          />
        </div>
        {record.type !== 4 && (
          <div className="form-group">
            <label>数值</label>
            <Input
              value={record.value}
              placeholder={record.type === 1 ? '单位：分' : record.type === 3 ? '单位：GB' : record.type === 5 ? '一次性套餐输入0' : '单位：天'}
              onChange={(e) => setRecord({ ...record, value: e.target.value })}
            />
          </div>
        )}
        {record.type === 5 && (
          <div className="form-group">
            <label>指定订阅</label>
            <Select
              style={{ width: '100%' }}
              value={record.plan_id}
              placeholder="请选择订阅"
              onChange={(value) => setRecord({ ...record, plan_id: value })}
              options={plans.map((plan) => ({ value: plan.id, label: plan.name }))}
            />
          </div>
        )}
        <div className="form-group">
          <label>礼品卡有效期</label>
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
