import React, { useEffect, useState } from 'react'
import { Button, Divider, Drawer, Input, Popconfirm, Select, Space, Switch, message } from 'antd'
import { PlusOutlined, MenuOutlined } from '@ant-design/icons'
import MarkdownEditor from 'react-markdown-editor-lite'
import MarkdownIt from 'markdown-it'
import 'react-markdown-editor-lite/lib/index.css'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { i18nText, time } from '../utils'

const mdParser = new MarkdownIt()

export default function Knowledge() {
  const [rows, setRows] = useState([])
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState({})

  const [dragIndex, setDragIndex] = useState(null)
  const [dropTarget, setDropTarget] = useState(null)

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
        setRecord(res.data || {})
        setOpen(true)
      }
    })
  }

  const save = () => {
    const params = { ...record }
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

  const saveSort = (list) => {
    api.post('/knowledge/sort', { knowledge_ids: list.map((item) => item.id) }).then(() => load())
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

  const columns = [
    {
      title: '排序',
      key: 'sort',
      width: 100,
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
    { title: '文章ID', dataIndex: 'id', key: 'id', width: 130 },
    {
      title: '显示',
      dataIndex: 'show',
      key: 'show',
      width: 100,
      render: (v, row) => <Switch size="small" checked={!!v} onChange={() => toggle(row.id)} />
    },
    { title: '标题', dataIndex: 'title', key: 'title', width: 220 },
    { title: '分类', dataIndex: 'category', key: 'category', width: 160 },
    {
      title: '更新时间',
      dataIndex: 'updated_at',
      key: 'updated_at',
      width: 220,
      align: 'right',
      render: (v) => time(v, 'YYYY/MM/DD HH:mm')
    },
    {
      title: '操作',
      key: 'action',
      width: 100,
      align: 'right',
      fixed: 'right',
      render: (_, row) => (
        <span>
          <a onClick={() => edit(row)}>编辑</a>
          <Divider type="vertical" />
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
          icon={<PlusOutlined />}
          onClick={() => {
            setRecord({ title: '', category: '', language: '', body: '' })
            setOpen(true)
          }}
        >
          新增
        </Button>
      }
    >
      <DataTable
        dataSource={rows}
        columns={columns}
        loading={loading}
        pagination={false}
        scroll={{ x: 1100 }}
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

      <Drawer
        title={record.id ? '编辑知识' : '新增知识'}
        open={open}
        onClose={() => setOpen(false)}
        width="80%"
        footer={
          <div className="text-right">
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
          <label htmlFor="example-text-input-alt">标题</label>
          <Input
            value={record.title}
            placeholder="请输入知识标题"
            onChange={(e) => setRecord({ ...record, title: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">分类</label>
          <Input
            value={record.category}
            placeholder="请输入分类，分类将会自动归集"
            onChange={(e) => setRecord({ ...record, category: e.target.value })}
            list="knowledge-categories"
          />
          <datalist id="knowledge-categories">
            {categories.map((item) => (
              <option key={item} value={item} />
            ))}
          </datalist>
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">语言</label>
          <Select
            style={{ width: '100%' }}
            value={record.language}
            placeholder="请选择知识语言"
            onChange={(value) => setRecord({ ...record, language: value })}
            options={Object.keys(i18nText).map((key) => ({ value: key, label: i18nText[key] }))}
          />
        </div>
        <div className="form-group">
          <label htmlFor="example-text-input-alt">内容</label>
          <MarkdownEditor
            value={record.body || ''}
            style={{ height: 500 }}
            renderHTML={(text) => mdParser.render(text)}
            onChange={({ text }) => setRecord({ ...record, body: text })}
          />
        </div>
      </Drawer>
    </TableBlock>
  )
}
