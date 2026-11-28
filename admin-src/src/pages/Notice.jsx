import React, { useEffect, useState } from 'react'
import { Button, Input, Modal, Select, message, Popconfirm, Switch } from 'antd'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { time } from '../utils'

const { TextArea } = Input

export default function Notice() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState({})

  const load = () => {
    setLoading(true)
    api.get('/notice/fetch').then((res) => {
      setLoading(false)
      if (res.code === 200) setRows(res.data || [])
    })
  }

  useEffect(load, [])

  const save = () => {
    if (!record.title || !record.content) {
      message.warning('标题和内容不能为空')
      return
    }
    setSaving(true)
    api.post('/notice/save', record).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setOpen(false)
        load()
      }
    })
  }

  const drop = (id) => {
    api.post('/notice/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load()
      }
    })
  }

  const toggle = (id) => {
    api.post('/notice/show', { id }).then((res) => {
      if (res.code === 200) load()
    })
  }

  const columns = [
    {
      title: '显示',
      dataIndex: 'show',
      key: 'show',
      width: 80,
      render: (v, row) => <Switch size="small" checked={!!v} onChange={() => toggle(row.id)} />
    },
    { title: '标题', dataIndex: 'title', key: 'title' },
    { title: '更新时间', dataIndex: 'updated_at', key: 'updated_at', render: (v) => time(v, 'YYYY/MM/DD HH:mm') },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <span>
          <a
            onClick={() => {
              setRecord({ ...row, tags: row.tags || [] })
              setOpen(true)
            }}
          >
            编辑
          </a>
          <span className="mx-2 text-muted">|</span>
          <Popconfirm title="确定要删除该公告吗？" onConfirm={() => drop(row.id)} okText="删除" cancelText="取消">
            <a className="text-danger">删除</a>
          </Popconfirm>
        </span>
      )
    }
  ]

  return (
    <TableBlock
      toolbar={
        <Button
          onClick={() => {
            setRecord({ title: '', content: '', tags: [], img_url: '' })
            setOpen(true)
          }}
        >
          添加公告
        </Button>
      }
    >
      <DataTable dataSource={rows} columns={columns} loading={loading} pagination={false} />
      <Modal
        title={record.id ? '编辑公告' : '新建公告'}
        open={open}
        onCancel={() => setOpen(false)}
        onOk={save}
        confirmLoading={saving}
        okText="提交"
        cancelText="取消"
        width={720}
      >
        <div className="form-group">
          <label>标题</label>
          <Input value={record.title} placeholder="请输入公告标题" onChange={(e) => setRecord({ ...record, title: e.target.value })} />
        </div>
        <div className="form-group">
          <label>公告内容</label>
          <TextArea rows={12} value={record.content} placeholder="请输入公告内容" onChange={(e) => setRecord({ ...record, content: e.target.value })} />
        </div>
        <div className="form-group">
          <label>公告标签</label>
          <Select
            mode="tags"
            style={{ width: '100%' }}
            value={record.tags || []}
            placeholder="输入后回车添加标签"
            onChange={(value) => setRecord({ ...record, tags: value.length ? value : null })}
          />
        </div>
        <div className="form-group">
          <label>图片URL</label>
          <Input value={record.img_url} placeholder="请输入图片URL" onChange={(e) => setRecord({ ...record, img_url: e.target.value })} />
        </div>
      </Modal>
    </TableBlock>
  )
}
