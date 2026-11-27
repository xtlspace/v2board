import React, { useEffect, useState } from 'react'
import { Button, Input, Modal, Select, Switch, message, Popconfirm, Space } from 'antd'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { i18nText, time } from '../utils'

const { TextArea } = Input

export default function Knowledge() {
  const [rows, setRows] = useState([])
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState({})

  const load = () => {
    setLoading(true)
    api.get('/knowledge/fetch').then((res) => {
      setLoading(false)
      if (res.code === 200) setRows(res.data || [])
    })
    api.get('/knowledge/getCategory').then((res) => {
      if (res.code === 200) setCategories(res.data || [])
    })
  }

  useEffect(load, [])

  const edit = (row) => {
    api.get('/knowledge/fetch', { id: row.id }).then((res) => {
      if (res.code === 200) {
        setRecord({ ...res.data, categoryList: res.data.category ? [res.data.category] : [] })
        setOpen(true)
      }
    })
  }

  const save = () => {
    const params = { ...record }
    delete params.categoryList
    if (!params.title || !params.category || !params.language || !params.body) {
      message.warning('请填写标题、分类、语言和内容')
      return
    }
    setSaving(true)
    api.post('/knowledge/save', params).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setOpen(false)
        load()
      }
    })
  }

  const drop = (id) => {
    api.post('/knowledge/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load()
      }
    })
  }

  const toggle = (id) => {
    api.post('/knowledge/show', { id }).then((res) => {
      if (res.code === 200) load()
    })
  }

  const move = (index, offset) => {
    const list = rows.slice()
    const target = index + offset
    if (target < 0 || target >= list.length) return
    ;[list[index], list[target]] = [list[target], list[index]]
    setRows(list)
    api.post('/knowledge/sort', { knowledge_ids: list.map((item) => item.id) }).then((res) => {
      if (res.code !== 200) load()
    })
  }

  const columns = [
    { title: '排序', key: 'sort', width: 110, render: (_, row, index) => (
      <Space size={2}>
        <a onClick={() => move(index, -1)}>↑</a>
        <span>{index + 1}</span>
        <a onClick={() => move(index, 1)}>↓</a>
      </Space>
    ) },
    { title: '文章ID', dataIndex: 'id', key: 'id' },
    { title: '标题', dataIndex: 'title', key: 'title' },
    { title: '分类', dataIndex: 'category', key: 'category' },
    { title: '显示', dataIndex: 'show', key: 'show', render: (v, row) => <Switch size="small" checked={!!v} onChange={() => toggle(row.id)} /> },
    { title: '更新时间', dataIndex: 'updated_at', key: 'updated_at', render: (v) => time(v, 'YYYY/MM/DD HH:mm') },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <span>
          <a onClick={() => edit(row)}>编辑</a>
          <span className="mx-2 text-muted">|</span>
          <Popconfirm title="确定要删除该条项目吗？" onConfirm={() => drop(row.id)} okText="删除" cancelText="取消">
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
            setRecord({ title: '', category: '', language: 'zh-CN', body: '', categoryList: [] })
            setOpen(true)
          }}
        >
          新增
        </Button>
      }
    >
      <DataTable dataSource={rows} columns={columns} loading={loading} pagination={false} />
      <Modal
        title={record.id ? '编辑知识' : '新增知识'}
        open={open}
        onCancel={() => setOpen(false)}
        onOk={save}
        confirmLoading={saving}
        okText="提交"
        cancelText="取消"
        width={760}
      >
        <div className="form-group">
          <label>标题</label>
          <Input value={record.title} placeholder="请输入知识标题" onChange={(e) => setRecord({ ...record, title: e.target.value })} />
        </div>
        <div className="form-group">
          <label>分类</label>
          <Select
            mode="tags"
            maxCount={1}
            style={{ width: '100%' }}
            value={record.categoryList || []}
            placeholder="请输入分类，分类将会自动归集"
            onChange={(value) => setRecord({ ...record, categoryList: value.slice(-1), category: value.slice(-1)[0] || '' })}
            options={categories.map((item) => ({ value: item, label: item }))}
          />
        </div>
        <div className="form-group">
          <label>语言</label>
          <Select
            style={{ width: '100%' }}
            value={record.language}
            placeholder="请选择知识语言"
            onChange={(value) => setRecord({ ...record, language: value })}
            options={Object.keys(i18nText).map((key) => ({ value: key, label: i18nText[key] }))}
          />
        </div>
        <div className="form-group">
          <label>内容</label>
          <TextArea rows={12} value={record.body} onChange={(e) => setRecord({ ...record, body: e.target.value })} />
        </div>
      </Modal>
    </TableBlock>
  )
}
