import React, { useEffect, useState } from 'react'
import { Button, Input, Modal, Space, message, Popconfirm } from 'antd'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { Field } from '../components/ui'

export default function ServerGroup() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState({ name: '' })

  const load = () => {
    setLoading(true)
    api.get('/server/group/fetch').then((res) => {
      setLoading(false)
      if (res.code === 200) setRows(res.data || [])
    })
  }

  useEffect(load, [])

  const save = () => {
    if (!record.name) {
      message.warning('组名不能为空')
      return
    }
    setSaving(true)
    api.post('/server/group/save', record).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setOpen(false)
        load()
      }
    })
  }

  const drop = (id) => {
    api.post('/server/group/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load()
      }
    })
  }

  const columns = [
    { title: '组ID', dataIndex: 'id', key: 'id' },
    { title: '组名称', dataIndex: 'name', key: 'name' },
    { title: '用户数量', dataIndex: 'user_count', key: 'user_count' },
    { title: '节点数量', dataIndex: 'server_count', key: 'server_count' },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <Space>
          <a
            onClick={() => {
              setRecord({ ...row })
              setOpen(true)
            }}
          >
            编辑
          </a>
          <Popconfirm title="确定要删除该权限组吗？" onConfirm={() => drop(row.id)} okText="删除" cancelText="取消">
            <a className="text-danger">删除</a>
          </Popconfirm>
        </Space>
      )
    }
  ]

  return (
    <TableBlock
      toolbar={
        <Button
          onClick={() => {
            setRecord({ name: '' })
            setOpen(true)
          }}
        >
          添加权限组
        </Button>
      }
    >
      <DataTable dataSource={rows} columns={columns} loading={loading} pagination={false} />
      <Modal title={record.id ? '编辑权限组' : '添加权限组'} open={open} onCancel={() => setOpen(false)} onOk={save} confirmLoading={saving} okText="提交" cancelText="取消">
        <Field label="组名称">
          <Input value={record.name} placeholder="请输入组名称" onChange={(e) => setRecord({ ...record, name: e.target.value })} />
        </Field>
      </Modal>
    </TableBlock>
  )
}
