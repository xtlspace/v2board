import React, { useEffect, useState } from 'react'
import { Button, Input, Radio, Space, message, Popconfirm } from 'antd'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { time, ticketLevelText } from '../utils'
import { adminBase } from '../settings'

export default function Ticket() {
  const navigate = useNavigate()
  const [rows, setRows] = useState([])
  const [status, setStatus] = useState(0)
  const [email, setEmail] = useState('')
  const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 })
  const [loading, setLoading] = useState(false)

  const load = (page = pagination, extra = {}) => {
    setLoading(true)
    const params = {
      current: page.current,
      pageSize: page.pageSize,
      status: extra.status !== undefined ? extra.status : status
    }
    const keyword = extra.email !== undefined ? extra.email : email
    if (keyword) params.email = keyword
    api.get('/ticket/fetch', params).then((res) => {
      setLoading(false)
      if (res.code === 200) {
        setRows(res.data || [])
        setPagination({ ...page, total: res.total || 0 })
      }
    })
  }

  useEffect(() => {
    load({ current: 1, pageSize: 10 })
  }, [])

  const close = (id) => {
    api.post('/ticket/close', { id }).then((res) => {
      if (res.code === 200) {
        message.success('已关闭')
        load(pagination)
      }
    })
  }

  const drop = (id) => {
    api.post('/ticket/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load(pagination)
      }
    })
  }

  const openTicket = (id) => {
    window.open(`${window.location.origin}${adminBase}/ticket/${id}`, '_blank')
  }

  const columns = [
    { title: '#', dataIndex: 'id', key: 'id' },
    { title: '主题', dataIndex: 'subject', key: 'subject' },
    { title: '工单级别', dataIndex: 'level', key: 'level', render: (v) => ticketLevelText[v] || v },
    {
      title: '工单状态',
      key: 'status',
      render: (_, row) =>
        row.status === 1 ? (
          <span>
            <span className="badge badge-success mr-1" /> 已关闭
          </span>
        ) : (
          <span>
            <span className={`badge ${row.reply_status ? 'badge-info' : 'badge-danger'} mr-1`} /> {row.reply_status ? '已回复' : '待回复'}
          </span>
        )
    },
    { title: '创建时间', dataIndex: 'created_at', key: 'created_at', render: (v) => time(v, 'YYYY/MM/DD HH:mm') },
    { title: '最后回复', dataIndex: 'updated_at', key: 'updated_at', render: (v) => time(v, 'YYYY/MM/DD HH:mm') },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <Space>
          <a onClick={() => openTicket(row.id)}>查看</a>
          {row.status ? (
            <Popconfirm title="确定要删除该工单吗？" onConfirm={() => drop(row.id)} okText="确定" cancelText="取消">
              <a className="text-danger">删除</a>
            </Popconfirm>
          ) : (
            <Popconfirm title="确定要关闭该工单吗？" onConfirm={() => close(row.id)} okText="确定" cancelText="取消">
              <a>关闭</a>
            </Popconfirm>
          )}
        </Space>
      )
    }
  ]

  return (
    <TableBlock
      toolbar={
        <div className="d-flex flex-wrap align-items-center justify-content-between">
          <Radio.Group
            value={status}
            onChange={(e) => {
              setStatus(e.target.value)
              setPagination({ ...pagination, current: 1 })
              load({ ...pagination, current: 1 }, { status: e.target.value })
            }}
          >
            <Radio.Button value={0}>已开启</Radio.Button>
            <Radio.Button value={1}>已关闭</Radio.Button>
          </Radio.Group>
          <Input.Search
            allowClear
            style={{ maxWidth: 260 }}
            placeholder="输入邮箱搜索"
            onSearch={(value) => {
              setEmail(value)
              setPagination({ ...pagination, current: 1 })
              load({ ...pagination, current: 1 }, { email: value })
            }}
          />
        </div>
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
    </TableBlock>
  )
}
