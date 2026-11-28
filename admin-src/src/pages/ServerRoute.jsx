import React, { useEffect, useState } from 'react'
import { Button, Input, Select, Space, message, Popconfirm } from 'antd'
import { api } from '../api/client'
import { DataTable, DrawerForm, TableBlock } from '../components/ui'
import { routeActionText } from '../utils'

const { TextArea } = Input

export default function ServerRoute() {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [record, setRecord] = useState({})

  const load = () => {
    setLoading(true)
    api.get('/server/route/fetch').then((res) => {
      setLoading(false)
      if (res.code === 200) setRows(res.data || [])
    })
  }

  useEffect(load, [])

  const matchText = (match) => {
    if (Array.isArray(match)) return match.join('\n')
    if (typeof match === 'string') return match
    return ''
  }

  const edit = (row) => {
    setRecord({ ...row, matchText: matchText(row.match) })
    setOpen(true)
  }

  const save = () => {
    const params = { ...record }
    params.match = (params.matchText || '')
      .split(/[\n,]/)
      .map((v) => v.trim())
      .filter(Boolean)
    delete params.matchText
    if (params.action === 'default_out') params.match = []
    if (!params.remarks) {
      message.warning('备注不能为空')
      return
    }
    setSaving(true)
    api.post('/server/route/save', params).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setOpen(false)
        load()
      }
    })
  }

  const drop = (id) => {
    api.post('/server/route/drop', { id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load()
      }
    })
  }

  const columns = [
    { title: 'ID', dataIndex: 'id', key: 'id' },
    { title: '备注', dataIndex: 'remarks', key: 'remarks' },
    {
      title: '匹配数量',
      key: 'match',
      render: (_, row) => {
        const count = Array.isArray(row.match) ? row.match.length : 0
        return count === 0 ? '无规则时默认' : `匹配 ${count} 条规则`
      }
    },
    { title: '动作', dataIndex: 'action', key: 'action', render: (v) => routeActionText[v] || v },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      render: (_, row) => (
        <Space>
          <a onClick={() => edit(row)}>编辑</a>
          <Popconfirm title="确定要删除该路由吗？" onConfirm={() => drop(row.id)} okText="删除" cancelText="取消">
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
            setRecord({ remarks: '', matchText: '', action: 'block' })
            setOpen(true)
          }}
        >
          添加路由
        </Button>
      }
    >
      <DataTable dataSource={rows} columns={columns} loading={loading} pagination={false} />
      <DrawerForm title={record.id ? '编辑路由' : '创建路由'} open={open} onClose={() => setOpen(false)} onSubmit={save} submitting={saving}>
        <div className="form-group">
          <label>备注</label>
          <Input value={record.remarks} placeholder="请输入备注" onChange={(e) => setRecord({ ...record, remarks: e.target.value })} />
        </div>
        {record.action !== 'default_out' && (
          <div className="form-group">
            <label>
              匹配值{' '}
              <a href="https://xtls.github.io/config/routing.html#ruleobject" target="_blank" rel="noreferrer">
                填写参考
              </a>
            </label>
            <TextArea
              rows={5}
              value={record.matchText}
              placeholder={'example.com(关键字匹配)\ndomain:example.com(子域名匹配)\ngeosite:netflix(预定义域名列表)'}
              onChange={(e) => setRecord({ ...record, matchText: e.target.value })}
            />
          </div>
        )}
        <div className="form-group">
          <label>动作</label>
          <Select
            style={{ width: '100%' }}
            value={record.action}
            placeholder="请选择动作"
            onChange={(value) => setRecord({ ...record, action: value })}
            options={Object.keys(routeActionText).map((key) => ({ value: key, label: routeActionText[key] }))}
          />
        </div>
        {record.action === 'dns' && (
          <div className="form-group">
            <label>DNS服务器</label>
            <Input
              value={record.action_value}
              placeholder="请输入用于解析的DNS服务器地址"
              onChange={(e) => setRecord({ ...record, action_value: e.target.value })}
            />
          </div>
        )}
        {['route', 'route_ip', 'default_out'].includes(record.action) && (
          <div className="form-group">
            <label>
              Xray出站配置{' '}
              <a href="https://xtls.github.io/config/outbound.html" target="_blank" rel="noreferrer">
                填写参考
              </a>
            </label>
            <TextArea
              rows={8}
              value={record.action_value}
              onChange={(e) => setRecord({ ...record, action_value: e.target.value })}
            />
          </div>
        )}
      </DrawerForm>
    </TableBlock>
  )
}
