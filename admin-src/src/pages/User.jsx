import React, { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Button,
  Input,
  InputNumber,
  Modal,
  Select,
  Switch,
  Space,
  message,
  Popconfirm,
  Dropdown,
  Menu,
  Drawer,
  DatePicker,
  Descriptions
} from 'antd'
import { FilterOutlined, DownOutlined } from '@ant-design/icons'
import { useNavigate } from 'react-router-dom'
import dayjs from 'dayjs'
import { api } from '../api/client'
import { DataTable, TableBlock } from '../components/ui'
import { copyText, download, money, time } from '../utils'
import { portraitUrl } from '../settings'

const GB = 1073741824

const filterKeys = [
  { key: 'email', title: '邮箱', condition: ['模糊'] },
  { key: 'id', title: '用户ID', condition: ['=', '>=', '>', '<', '<='] },
  { key: 'plan_id', title: '订阅', condition: ['=', '!='], type: 'plan' },
  { key: 'transfer_enable', title: '流量', condition: ['>=', '>', '<', '<='] },
  { key: 'd', title: '下行', condition: ['>=', '>', '<', '<='] },
  { key: 'expired_at', title: '到期时间', condition: ['>=', '>', '<', '<='], type: 'date' },
  { key: 'uuid', title: 'UUID', condition: ['='] },
  { key: 'token', title: 'TOKEN', condition: ['='] },
  { key: 'banned', title: '账号状态', condition: ['='], type: 'select', options: [{ label: '正常', value: 0 }, { label: '封禁', value: 1 }] },
  { key: 'invite_by_email', title: '邀请人邮箱', condition: ['模糊'] },
  { key: 'invite_user_id', title: '邀请人ID', condition: ['='] },
  { key: 'remarks', title: '备注', condition: ['模糊', '不包含'] },
  { key: 'is_admin', title: '管理员', condition: ['='], type: 'select', options: [{ label: '是', value: 1 }, { label: '否', value: 0 }] }
]

function convertUser(user) {
  return {
    ...user,
    password: '',
    transfer_enable: (Number(user.transfer_enable || 0) / GB).toFixed(2),
    u: (Number(user.u || 0) / GB).toFixed(2),
    d: (Number(user.d || 0) / GB).toFixed(2),
    total_used: (Number(user.total_used || 0) / GB).toFixed(2),
    balance: (Number(user.balance || 0) / 100).toFixed(2),
    commission_balance: (Number(user.commission_balance || 0) / 100).toFixed(2),
    invite_user_email: user.invite_user ? user.invite_user.email : ''
  }
}

export default function User() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [rows, setRows] = useState([])
  const [groups, setGroups] = useState([])
  const [plans, setPlans] = useState([])
  const [pagination, setPagination] = useState({ current: 1, pageSize: 15, total: 0 })
  const [filters, setFilters] = useState([])
  const [sort, setSort] = useState({ sort: 'created_at', sort_type: 'DESC' })
  const [loading, setLoading] = useState(false)
  const [isMobile, setIsMobile] = useState(() => window.matchMedia('(max-width: 767.98px)').matches)
  const [jumpPage, setJumpPage] = useState(null)

  useEffect(() => {
    const query = window.matchMedia('(max-width: 767.98px)')
    const handler = (event) => setIsMobile(event.matches)
    if (query.addEventListener) query.addEventListener('change', handler)
    else query.addListener(handler)
    return () => {
      if (query.removeEventListener) query.removeEventListener('change', handler)
      else query.removeListener(handler)
    }
  }, [])

  const [filterOpen, setFilterOpen] = useState(false)
  const [draftFilters, setDraftFilters] = useState([])

  const [editOpen, setEditOpen] = useState(false)
  const [editRecord, setEditRecord] = useState(null)
  const [saving, setSaving] = useState(false)

  const [mailOpen, setMailOpen] = useState(false)
  const [mail, setMail] = useState({ subject: '', content: '' })

  const [ticketOpen, setTicketOpen] = useState(false)
  const [ticket, setTicket] = useState({ subject: '', level: 1, message: '', userId: null })

  const [trafficOpen, setTrafficOpen] = useState(false)
  const [traffic, setTraffic] = useState({ rows: [], total: 0, loading: false, email: '', userId: 0 })
  const [trafficPage, setTrafficPage] = useState({ current: 1, pageSize: 10 })

  const [assignOpen, setAssignOpen] = useState(false)
  const [assign, setAssign] = useState({ email: '', plan_id: undefined, period: 'month_price', total_amount: '' })

  const [generateOpen, setGenerateOpen] = useState(false)
  const [generate, setGenerate] = useState({ email_prefix: '', email_suffix: '', password: '', plan_id: undefined, generate_count: '', expired_at: null })

  const load = (page = pagination, activeFilters = filters, activeSort = sort) => {
    setLoading(true)
    const params = { current: page.current, pageSize: page.pageSize, ...activeSort }
    if (activeFilters.length) params.filter = activeFilters
    api.get('/user/fetch', params).then((res) => {
      setLoading(false)
      if (res.code === 200) {
        setRows((res.data || []).map(convertUser))
        setPagination({ ...page, total: res.total || 0 })
      }
    })
  }

  useEffect(() => {
    const userId = searchParams.get('user_id')
    const initial = userId ? [{ key: 'id', condition: '=', value: userId }] : []
    setFilters(initial)
    setDraftFilters(initial)
    load({ current: 1, pageSize: pagination.pageSize }, initial)
    Promise.all([api.get('/server/group/fetch'), api.get('/plan/fetch')]).then(([groupRes, planRes]) => {
      if (groupRes.code === 200) setGroups(groupRes.data || [])
      if (planRes.code === 200) setPlans(planRes.data || [])
    })
    if (userId && searchParams.get('traffic')) {
      openTraffic({ id: Number(userId), email: `#${userId}` })
    }
  }, [])

  const applyFilter = () => {
    const valid = draftFilters.filter((item) => item.key && item.condition && item.value !== '')
    setFilters(valid)
    setFilterOpen(false)
    setPagination({ ...pagination, current: 1 })
    load({ ...pagination, current: 1 }, valid)
  }

  const edit = (row) => {
    api.get('/user/getUserInfoById', { id: row.id }).then((res) => {
      if (res.code === 200) {
        setEditRecord(convertUser(res.data))
        setEditOpen(true)
      }
    })
  }

  const saveEdit = () => {
    const record = { ...editRecord }
    ;['total_used', 'plan_name', 'subscribe_url', 'alive_ip', 'ips', 'invite_user', 't', 'created_at', 'updated_at'].forEach((key) => delete record[key])
    if (!record.password) delete record.password
    record.transfer_enable = Math.round(Number(record.transfer_enable) * GB)
    record.u = Math.round(Number(record.u) * GB)
    record.d = Math.round(Number(record.d) * GB)
    record.balance = Math.round(Number(record.balance) * 100)
    record.commission_balance = Math.round(Number(record.commission_balance) * 100)
    record.banned = Number(record.banned)
    record.is_admin = Number(record.is_admin)
    record.is_staff = Number(record.is_staff)
    record.expired_at = record.expired_at ? dayjs(record.expired_at * 1000).unix() : null
    setSaving(true)
    api.post('/user/update', record).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setEditOpen(false)
        load(pagination)
      }
    })
  }

  const resetSecret = (row) => {
    api.post('/user/resetSecret', { id: row.id }).then((res) => {
      if (res.code === 200) {
        message.success('重置成功')
        load(pagination)
      }
    })
  }

  const delUser = (row) => {
    api.post('/user/delUser', { id: row.id }).then((res) => {
      if (res.code === 200) {
        message.success('删除成功')
        load(pagination)
      }
    })
  }

  const loginAs = (row) => {
    api.post('/user/loginAs', { id: row.id }).then((res) => {
      if (res.code === 200 && res.data && res.data.auth_data) {
        window.localStorage.setItem('authorization', res.data.auth_data)
        window.open('/')
      }
    })
  }

  const openTicket = (row) => {
    setTicket({ subject: '', level: 1, message: '', userId: row.id })
    setTicketOpen(true)
  }

  const saveTicket = () => {
    if (!ticket.subject || !ticket.message) {
      message.warning('请填写工单主题和内容')
      return
    }
    api.post('/ticket/save', { user_id: ticket.userId, subject: ticket.subject, level: ticket.level, message: ticket.message }).then((res) => {
      if (res.code === 200) {
        message.success('工单已创建')
        setTicketOpen(false)
      }
    })
  }

  const openTraffic = (row) => {
    setTraffic({ rows: [], total: 0, loading: true, email: row.email, userId: row.id })
    setTrafficOpen(true)
    setTrafficPage({ current: 1, pageSize: 10 })
    api.get('/stat/getStatUser', { user_id: row.id, current: 1, pageSize: 10 }).then((res) => {
      if (res.code === 200) setTraffic({ rows: res.data || [], total: res.total || 0, loading: false, email: row.email, userId: row.id })
      else setTraffic({ rows: [], total: 0, loading: false, email: row.email, userId: row.id })
    })
  }

  const loadTraffic = (page) => {
    setTrafficPage(page)
    api.get('/stat/getStatUser', { user_id: traffic.userId, current: page.current, pageSize: page.pageSize }).then((res) => {
      if (res.code === 200) setTraffic({ ...traffic, rows: res.data || [], total: res.total || 0, loading: false })
    })
  }

  const exportCsv = () => {
    const params = {}
    if (filters.length) params.filter = filters
    api.post('/user/dumpCSV', params).then((res) => {
      if (res.text) download('users.csv', res.text)
    })
  }

  const sendMail = () => {
    if (!mail.subject || !mail.content) {
      message.warning('请填写主题和内容')
      return
    }
    api.post('/user/sendMail', { ...mail, filter: filters }).then((res) => {
      if (res.code === 200) {
        message.success('发送任务已提交')
        setMailOpen(false)
      }
    })
  }

  const banAll = () => {
    if (!filters.length) {
      message.warning('请先设置过滤器，避免误操作全部用户')
      return
    }
    api.post('/user/ban', { filter: filters }).then((res) => {
      if (res.code === 200) {
        message.success('批量封禁完成')
        load(pagination)
      }
    })
  }

  const delAll = () => {
    if (!filters.length) {
      message.warning('请先设置过滤器，避免误操作全部用户')
      return
    }
    api.post('/user/allDel', { filter: filters }).then((res) => {
      if (res.code === 200) {
        message.success('批量删除完成')
        load(pagination)
      }
    })
  }

  const submitGenerate = () => {
    if (!generate.email_suffix) {
      message.warning('请输入邮箱后缀')
      return
    }
    const params = { ...generate }
    if (!params.email_prefix) delete params.email_prefix
    if (!params.password) delete params.password
    if (!params.plan_id) delete params.plan_id
    if (!params.generate_count) delete params.generate_count
    if (params.expired_at) params.expired_at = dayjs(params.expired_at).unix()
    else delete params.expired_at
    api.post('/user/generate', params).then((res) => {
      if (res.code === 200) {
        if (res.text) download('users.csv', res.text)
        message.success('生成成功')
        setGenerateOpen(false)
        load(pagination)
      }
    })
  }

  const submitAssign = () => {
    api.post('/order/assign', assign).then((res) => {
      if (res.code === 200) {
        message.success('订单已分配')
        setAssignOpen(false)
      }
    })
  }

  const columns = [
    { title: 'ID', dataIndex: 'id', key: 'id', sorter: true },
    {
      title: '邮箱',
      dataIndex: 'email',
      key: 'email',
      render: (v, row) => (
        <span>
          <span className={`badge ${Date.now() / 1000 - 600 > (row.t || 0) ? 'badge-secondary' : 'badge-success'} mr-1`} />
          {v}
        </span>
      )
    },
    { title: '状态', dataIndex: 'banned', key: 'banned', sorter: true, render: (v) => <span className={`badge badge-${v ? 'danger' : 'success'}`}>{v ? '封禁' : '正常'}</span> },
    { title: '订阅', dataIndex: 'plan_name', key: 'plan_id', sorter: true, render: (v) => v || '-' },
    {
      title: '权限组',
      dataIndex: 'group_id',
      key: 'group_id',
     
      sorter: true,
      render: (v) => {
        const group = groups.find((item) => item.id === Number(v))
        return group ? group.name : '-'
      }
    },
    {
      title: '已用(G)',
      dataIndex: 'total_used',
      key: 'total_used',
      sorter: true,
      render: (v, row) => <span className={`badge badge-${Number(v) > Number(row.transfer_enable) ? 'danger' : 'success'}`}>{v}</span>
    },
    { title: '流量(G)', dataIndex: 'transfer_enable', key: 'transfer_enable', sorter: true },
    {
      title: '设备数',
      key: 'alive_ip',
      render: (_, row) => (
        <span title={row.ips || ''}>
          {row.alive_ip || 0} / {row.device_limit !== null && row.device_limit !== undefined ? row.device_limit : '∞'}
        </span>
      )
    },
    {
      title: '到期时间',
      dataIndex: 'expired_at',
      key: 'expired_at',
      sorter: true,
      render: (v) => (
        <span className={`badge badge-${v && v * 1000 < Date.now() ? 'danger' : 'success'}`}>
          {v ? time(v, 'YYYY/MM/DD HH:mm') : '长期有效'}
        </span>
      )
    },
    { title: '余额', dataIndex: 'balance', key: 'balance', sorter: true },
    { title: '佣金', dataIndex: 'commission_balance', key: 'commission_balance', sorter: true },
    {
      title: '操作',
      key: 'action',
      align: 'right',
      fixed: 'right',
      render: (_, row) => (
        <Dropdown
          trigger={['click']}
          overlay={
            <Menu
              items={[
                { key: 'edit', label: '编辑', onClick: () => edit(row) },
                { key: 'portrait', label: 'TA的画像', onClick: () => window.open(`${portraitUrl}?tab=detail&user_id=${row.id}`, '_blank') },
                { key: 'assign', label: '分配订单', onClick: () => { setAssign({ email: row.email, plan_id: undefined, period: 'month_price', total_amount: '' }); setAssignOpen(true) } },
                { key: 'copy', label: '复制订阅URL', onClick: () => copyText(row.subscribe_url) },
                { key: 'reset', label: '重置UUID及订阅URL', onClick: () => resetSecret(row) },
                { key: 'orders', label: 'TA的订单', onClick: () => navigate(`/order?user_id=${row.id}`) },
                {
                  key: 'invites',
                  label: 'TA的邀请',
                  onClick: () => {
                    const next = [{ key: 'invite_user_id', condition: '=', value: row.id }]
                    setFilters(next)
                    setDraftFilters(next)
                    load({ ...pagination, current: 1 }, next)
                  }
                },
                { key: 'ticket', label: '开启工单', onClick: () => openTicket(row) },
                { key: 'traffic', label: 'TA的流量记录', onClick: () => openTraffic(row) },
                { key: 'login', label: '以该用户登录', onClick: () => loginAs(row) },
                { key: 'delete', label: '删除用户', danger: true, onClick: () => Modal.confirm({ title: '删除用户', content: `确定要删除 ${row.email} 的用户信息吗？`, okText: '确定', cancelText: '取消', onOk: () => delUser(row) }) }
              ]}
            />
          }
        >
          <a>
            操作 <DownOutlined />
          </a>
        </Dropdown>
      )
    }
  ]

  const renderFilterEditor = () => (
    <>
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
            ) : meta.type === 'plan' ? (
              <Select
                style={{ width: '100%', marginTop: 8 }}
                value={item.value === '' ? undefined : item.value}
                placeholder="请选择订阅"
                onChange={(value) => {
                  const next = draftFilters.slice()
                  next[index] = { ...item, value }
                  setDraftFilters(next)
                }}
                options={[{ value: 'null', label: '无订阅' }, ...plans.map((p) => ({ value: p.id, label: p.name }))]}
              />
            ) : meta.type === 'date' ? (
              <DatePicker
                style={{ width: '100%', marginTop: 8 }}
                showTime
                onChange={(value) => {
                  const next = draftFilters.slice()
                  next[index] = { ...item, value: value ? value.unix() : '' }
                  setDraftFilters(next)
                }}
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
      <Button block type="dashed" onClick={() => setDraftFilters([...draftFilters, { key: 'email', condition: '模糊', value: '' }])}>
        添加过滤条件
      </Button>
    </>
  )

  return (
    <TableBlock
      className="v2board-user-table"
      toolbar={
        <Space>
          <Button type={filters.length ? 'primary' : 'default'} icon={<FilterOutlined />} onClick={() => { setDraftFilters(filters.length ? filters : []); setFilterOpen(true) }}>
            过滤器
          </Button>
          <Dropdown
            overlay={
              <Menu
                items={[
                  { key: 'generate', label: '生成用户', onClick: () => setGenerateOpen(true) },
                  { key: 'csv', label: '导出CSV', onClick: exportCsv },
                  { key: 'mail', label: '发送邮件', onClick: () => setMailOpen(true) },
                  { key: 'ban', label: '批量封禁', disabled: !filters.length, onClick: banAll },
                  { key: 'del', label: '批量删除', danger: true, disabled: !filters.length, onClick: () => Modal.confirm({ title: '提醒', content: '确定要删除过滤后的用户吗？', okText: '确定', cancelText: '取消', onOk: delAll }) }
                ]}
              />
            }
          >
            <Button>操作 <DownOutlined /></Button>
          </Dropdown>
        </Space>
      }
    >
      <DataTable
        dataSource={rows}
        columns={columns}
        loading={loading}
        scroll={{ x: 'max-content' }}
        pagination={
          isMobile
            ? false
            : {
                ...pagination,
                showSizeChanger: true,
                showQuickJumper: true,
                pageSizeOptions: [15, 50, 100],
                showTotal: (total) => `共 ${total} 条`,
                onChange: (current, pageSize) => load({ current, pageSize })
              }
        }
        onChange={(nextPagination, _, sorter, extra) => {
          if (!extra || extra.action !== 'sort') return
          const nextSort = {
            sort: sorter && sorter.columnKey ? sorter.columnKey : 'created_at',
            sort_type: sorter && sorter.order === 'ascend' ? 'ASC' : 'DESC'
          }
          setSort(nextSort)
          load({ current: 1, pageSize: nextPagination.pageSize || pagination.pageSize }, filters, nextSort)
        }}
      />

      {isMobile ? (
        <div className="v2board-mobile-pager">
          <div className="v2board-mobile-pager-row">
            <Button disabled={pagination.current <= 1} onClick={() => load({ current: pagination.current - 1, pageSize: pagination.pageSize })}>
              上一页
            </Button>
            <span>
              第 {pagination.current} / {Math.max(1, Math.ceil((pagination.total || 0) / pagination.pageSize))} 页
            </span>
            <Button
              disabled={pagination.current >= Math.max(1, Math.ceil((pagination.total || 0) / pagination.pageSize))}
              onClick={() => load({ current: pagination.current + 1, pageSize: pagination.pageSize })}
            >
              下一页
            </Button>
          </div>
          <div className="v2board-mobile-pager-row">
            <span>共 {pagination.total || 0} 条</span>
            <span>每页</span>
            <Select
              value={pagination.pageSize}
              options={[15, 50, 100].map((size) => ({ value: size, label: `${size} 条` }))}
              onChange={(pageSize) => load({ current: 1, pageSize })}
              style={{ width: 96 }}
            />
            <span>跳至</span>
            <InputNumber
              min={1}
              max={Math.max(1, Math.ceil((pagination.total || 0) / pagination.pageSize))}
              value={jumpPage}
              onChange={setJumpPage}
              onPressEnter={() => {
                const page = Number(jumpPage)
                if (page >= 1 && page <= Math.ceil((pagination.total || 0) / pagination.pageSize)) {
                  load({ current: page, pageSize: pagination.pageSize })
                }
              }}
              style={{ width: 72 }}
            />
            <span>页</span>
          </div>
        </div>
      ) : null}

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
        {renderFilterEditor()}
      </Drawer>

      <Drawer
        title={editRecord ? `编辑用户 #${editRecord.id}` : '编辑用户'}
        open={editOpen}
        onClose={() => setEditOpen(false)}
        width={620}
        footer={
          <div className="text-right">
            <Space>
              <Button onClick={() => setEditOpen(false)}>取消</Button>
              <Button type="primary" loading={saving} onClick={saveEdit}>提交</Button>
            </Space>
          </div>
        }
      >
        {editRecord ? (
          <div>
            <div className="form-group">
              <label>邮箱</label>
              <Input value={editRecord.email} onChange={(e) => setEditRecord({ ...editRecord, email: e.target.value })} />
            </div>
            <div className="form-group">
              <label>密码（留空则不修改）</label>
              <Input.Password value={editRecord.password} onChange={(e) => setEditRecord({ ...editRecord, password: e.target.value })} />
            </div>
            <div className="form-group">
              <label>订阅</label>
              <Select
                allowClear
                style={{ width: '100%' }}
                value={editRecord.plan_id || undefined}
                onChange={(value) => {
                  const plan = plans.find((p) => p.id === value)
                  setEditRecord({ ...editRecord, plan_id: value || null, group_id: plan ? plan.group_id : editRecord.group_id })
                }}
                options={plans.map((p) => ({ value: p.id, label: p.name }))}
              />
            </div>
            <div className="form-group">
              <label>权限组</label>
              <Select
                style={{ width: '100%' }}
                value={editRecord.group_id || undefined}
                onChange={(value) => setEditRecord({ ...editRecord, group_id: value })}
                options={groups.map((g) => ({ value: g.id, label: g.name }))}
              />
            </div>
            <div className="row">
              <div className="col-6">
                <div className="form-group">
                  <label>套餐流量 (GB)</label>
                  <Input value={editRecord.transfer_enable} onChange={(e) => setEditRecord({ ...editRecord, transfer_enable: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>设备数限制</label>
                  <Input value={editRecord.device_limit} onChange={(e) => setEditRecord({ ...editRecord, device_limit: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>上行流量 (GB)</label>
                  <Input value={editRecord.u} onChange={(e) => setEditRecord({ ...editRecord, u: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>下行流量 (GB)</label>
                  <Input value={editRecord.d} onChange={(e) => setEditRecord({ ...editRecord, d: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>余额</label>
                  <Input value={editRecord.balance} onChange={(e) => setEditRecord({ ...editRecord, balance: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>佣金</label>
                  <Input value={editRecord.commission_balance} onChange={(e) => setEditRecord({ ...editRecord, commission_balance: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>推荐返利比例 (%)</label>
                  <Input value={editRecord.commission_rate} onChange={(e) => setEditRecord({ ...editRecord, commission_rate: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>专属折扣 (%)</label>
                  <Input value={editRecord.discount} onChange={(e) => setEditRecord({ ...editRecord, discount: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>限速 (Mbps)</label>
                  <Input value={editRecord.speed_limit} onChange={(e) => setEditRecord({ ...editRecord, speed_limit: e.target.value })} />
                </div>
              </div>
              <div className="col-6">
                <div className="form-group">
                  <label>邀请人邮箱</label>
                  <Input value={editRecord.invite_user_email} onChange={(e) => setEditRecord({ ...editRecord, invite_user_email: e.target.value })} />
                </div>
              </div>
            </div>
            <div className="form-group">
              <label>到期时间</label>
              <DatePicker
                showTime
                style={{ width: '100%' }}
                value={editRecord.expired_at ? dayjs(editRecord.expired_at * 1000) : null}
                onChange={(value) => setEditRecord({ ...editRecord, expired_at: value ? value.unix() : null })}
              />
            </div>
            <div className="form-group">
              <label>备注</label>
              <Input value={editRecord.remarks} onChange={(e) => setEditRecord({ ...editRecord, remarks: e.target.value })} />
            </div>
            <div className="form-group">
              <Space size="large">
                <span>
                  封禁 <Switch checked={!!Number(editRecord.banned)} onChange={(v) => setEditRecord({ ...editRecord, banned: v ? 1 : 0 })} />
                </span>
                <span>
                  管理员 <Switch checked={!!Number(editRecord.is_admin)} onChange={(v) => setEditRecord({ ...editRecord, is_admin: v ? 1 : 0 })} />
                </span>
                <span>
                  员工 <Switch checked={!!Number(editRecord.is_staff)} onChange={(v) => setEditRecord({ ...editRecord, is_staff: v ? 1 : 0 })} />
                </span>
              </Space>
            </div>
          </div>
        ) : null}
      </Drawer>

      <Modal title="发送邮件" open={mailOpen} onCancel={() => setMailOpen(false)} onOk={sendMail} okText="提交" cancelText="取消" width={620}>
        <div className="form-group">
          <label>主题</label>
          <Input value={mail.subject} onChange={(e) => setMail({ ...mail, subject: e.target.value })} />
        </div>
        <div className="form-group">
          <label>内容</label>
          <Input.TextArea rows={8} value={mail.content} onChange={(e) => setMail({ ...mail, content: e.target.value })} />
        </div>
        <div className="text-muted">将对当前过滤器命中的用户发送邮件（未设置过滤器时为全部用户）。</div>
      </Modal>

      <Modal title="开启工单" open={ticketOpen} onCancel={() => setTicketOpen(false)} onOk={saveTicket} okText="提交" cancelText="取消">
        <div className="form-group">
          <label>主题</label>
          <Input value={ticket.subject} placeholder="请输入工单主题" onChange={(e) => setTicket({ ...ticket, subject: e.target.value })} />
        </div>
        <div className="form-group">
          <label>等级</label>
          <Select
            style={{ width: '100%' }}
            value={ticket.level}
            onChange={(value) => setTicket({ ...ticket, level: value })}
            options={[{ value: 0, label: '低' }, { value: 1, label: '中' }, { value: 2, label: '高' }]}
          />
        </div>
        <div className="form-group">
          <label>内容</label>
          <Input.TextArea rows={6} value={ticket.message} placeholder="请描述问题" onChange={(e) => setTicket({ ...ticket, message: e.target.value })} />
        </div>
      </Modal>

      <Modal title={`流量记录 ${traffic.email}`} open={trafficOpen} onCancel={() => setTrafficOpen(false)} footer={null} width={720}>
        <DataTable
          loading={traffic.loading}
          dataSource={traffic.rows}
          pagination={{
            current: trafficPage.current,
            pageSize: trafficPage.pageSize,
            total: traffic.total,
            onChange: (current, pageSize) => loadTraffic({ current, pageSize })
          }}
          columns={[
            { title: '记录时间', dataIndex: 'record_at', render: (v) => time(v, 'YYYY/MM/DD HH:mm') },
            { title: '上行', dataIndex: 'u', render: (v) => `${(v / GB).toFixed(2)} GB` },
            { title: '下行', dataIndex: 'd', render: (v) => `${(v / GB).toFixed(2)} GB` },
            { title: '倍率', dataIndex: 'server_rate', render: (v) => v || 1 }
          ]}
        />
      </Modal>

      <Modal title="分配订单" open={assignOpen} onCancel={() => setAssignOpen(false)} onOk={submitAssign} okText="提交" cancelText="取消">
        <div className="form-group">
          <label>用户邮箱</label>
          <Input value={assign.email} onChange={(e) => setAssign({ ...assign, email: e.target.value })} />
        </div>
        <div className="form-group">
          <label>订阅计划</label>
          <Select
            style={{ width: '100%' }}
            value={assign.plan_id}
            onChange={(value) => setAssign({ ...assign, plan_id: value })}
            options={plans.map((p) => ({ value: p.id, label: p.name }))}
          />
        </div>
        <div className="form-group">
          <label>周期</label>
          <Select
            style={{ width: '100%' }}
            value={assign.period}
            onChange={(value) => setAssign({ ...assign, period: value })}
            options={[
              { value: 'month_price', label: '月付' },
              { value: 'quarter_price', label: '季付' },
              { value: 'half_year_price', label: '半年付' },
              { value: 'year_price', label: '年付' },
              { value: 'two_year_price', label: '两年付' },
              { value: 'three_year_price', label: '三年付' },
              { value: 'onetime_price', label: '一次性' },
              { value: 'reset_price', label: '流量重置包' }
            ]}
          />
        </div>
        <div className="form-group">
          <label>支付金额（分）</label>
          <Input value={assign.total_amount} onChange={(e) => setAssign({ ...assign, total_amount: e.target.value })} />
        </div>
      </Modal>

      <Modal title="生成用户" open={generateOpen} onCancel={() => setGenerateOpen(false)} onOk={submitGenerate} okText="提交" cancelText="取消">
        <div className="form-group">
          <label>邮箱前缀（留空则随机生成）</label>
          <Input value={generate.email_prefix} onChange={(e) => setGenerate({ ...generate, email_prefix: e.target.value })} />
        </div>
        <div className="form-group">
          <label>邮箱后缀</label>
          <Input addonBefore="@" value={generate.email_suffix} placeholder="example.com" onChange={(e) => setGenerate({ ...generate, email_suffix: e.target.value })} />
        </div>
        <div className="form-group">
          <label>生成数量（批量生成时前缀留空）</label>
          <Input value={generate.generate_count} onChange={(e) => setGenerate({ ...generate, generate_count: e.target.value })} />
        </div>
        <div className="form-group">
          <label>密码（留空则使用邮箱）</label>
          <Input.Password value={generate.password} onChange={(e) => setGenerate({ ...generate, password: e.target.value })} />
        </div>
        <div className="form-group">
          <label>订阅计划</label>
          <Select
            allowClear
            style={{ width: '100%' }}
            value={generate.plan_id}
            onChange={(value) => setGenerate({ ...generate, plan_id: value })}
            options={plans.map((p) => ({ value: p.id, label: p.name }))}
          />
        </div>
        <div className="form-group">
          <label>到期时间</label>
          <DatePicker showTime style={{ width: '100%' }} onChange={(value) => setGenerate({ ...generate, expired_at: value })} />
        </div>
      </Modal>
    </TableBlock>
  )
}
