import React, { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Button, Input, message, Spin } from 'antd'
import { api } from '../api/client'
import { Block } from '../components/ui'
import { portraitUrl as portraitBase } from '../settings'
import { time, ticketLevelText, ticketStatusText } from '../utils'

const { TextArea } = Input

export default function TicketDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [ticket, setTicket] = useState(null)
  const [user, setUser] = useState({})
  const [reply, setReply] = useState('')
  const [loading, setLoading] = useState(false)
  const [sending, setSending] = useState(false)

  const load = () => {
    setLoading(true)
    api.get('/ticket/fetch', { id }).then((res) => {
      setLoading(false)
      if (res.code === 200) {
        setTicket(res.data)
        if (res.data && res.data.user_id) {
          api.get('/user/getUserInfoById', { id: res.data.user_id }).then((userRes) => {
            if (userRes.code === 200) setUser(userRes.data || {})
          })
        }
      }
    })
  }

  useEffect(load, [id])

  const send = () => {
    if (!reply.trim()) {
      message.warning('请输入回复内容')
      return
    }
    setSending(true)
    api.post('/ticket/reply', { id, message: reply }).then((res) => {
      setSending(false)
      if (res.code === 200) {
        setReply('')
        message.success('回复成功')
        load()
      }
    })
  }

  const portraitUrl = `${portraitBase}?tab=detail&user_id=${ticket ? ticket.user_id : ''}`

  return (
    <Spin spinning={loading}>
      <div className="mb-3">
        <Button onClick={() => navigate('/ticket')}>返回工单列表</Button>
      </div>
      <Block title={ticket ? `#${ticket.id} ${ticket.subject}` : '工单详情'}>
        {ticket ? (
          <div>
            <div className="d-flex flex-wrap align-items-center mb-3" style={{ gap: 16 }}>
              <span>用户：{user.email || `#${ticket.user_id}`}</span>
              <span>级别：{ticketLevelText[ticket.level] || ticket.level}</span>
              <span>状态：{ticketStatusText[ticket.status] || ticket.status}</span>
              <span>创建：{time(ticket.created_at, 'YYYY/MM/DD HH:mm')}</span>
              <a href={portraitUrl} target="_blank" rel="noreferrer">TA的画像</a>
              <a onClick={() => navigate(`/order?user_id=${ticket.user_id}`)}>他的订单</a>
              <a onClick={() => navigate(`/user?user_id=${ticket.user_id}&traffic=1`)}>他的流量记录</a>
            </div>
            <div className="v2board-ticket-messages">
              {(ticket.message || []).map((item) => (
                <div
                  key={item.id}
                  className={`p-3 mb-3 rounded v2board-ticket-msg ${item.is_me ? 'v2board-bubble-me' : 'v2board-bubble-user'}`}
                  style={{ marginLeft: item.is_me ? 40 : 0, marginRight: item.is_me ? 0 : 40 }}
                >
                  <div className="text-muted font-size-sm mb-2">
                    {item.is_me ? '管理员' : '用户'} · {time(item.created_at, 'YYYY/MM/DD HH:mm')}
                  </div>
                  <div style={{ whiteSpace: 'pre-wrap' }}>{item.message}</div>
                </div>
              ))}
              {!ticket.message || !ticket.message.length ? <div className="text-muted py-4">暂无消息</div> : null}
            </div>
            <div className="mt-4">
              <TextArea rows={5} value={reply} placeholder="输入内容回复工单" onChange={(e) => setReply(e.target.value)} />
              <div className="mt-2 text-right">
                <Button type="primary" loading={sending} onClick={send}>
                  回复
                </Button>
              </div>
            </div>
          </div>
        ) : (
          <div className="text-muted">工单不存在</div>
        )}
      </Block>
    </Spin>
  )
}
