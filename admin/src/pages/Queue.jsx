import React, { useEffect, useState } from 'react'
import { Spin } from 'antd'
import { api } from '../api/client'
import { Block, DataTable } from '../components/ui'

const queueNames = {
  order_handle: '订单队列',
  send_email: '邮件队列',
  send_email_mass: '邮件群发队列',
  send_telegram: 'Telegram消息队列',
  stat: '统计队列',
  traffic_fetch: '流量消费队列'
}

export default function Queue() {
  const [stats, setStats] = useState(null)
  const [workload, setWorkload] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Promise.all([api.get('/system/getQueueStats'), api.get('/system/getQueueWorkload')]).then(([statsRes, workRes]) => {
      if (statsRes.code === 200) setStats(statsRes.data || {})
      if (workRes.code === 200) setWorkload((workRes.data || []).filter((item) => item.name !== 'default'))
      setLoading(false)
    })
  }, [])

  const cards = [
    ['当前作业量', stats ? stats.jobsPerMinute || 0 : 0],
    ['近一小时处理量', stats ? stats.recentJobs || 0 : 0],
    ['7日内报错数量', stats ? stats.failedJobs || 0 : 0]
  ]

  return (
    <Spin spinning={loading}>
      <Block title="总览">
        <div className="row no-gutters">
          {cards.map(([label, value], index) => (
            <div className={`col-lg-6 col-xl-3 p-4 ${index < 3 ? 'border-right' : ''} border-bottom`} key={label}>
              <div>{label}</div>
              <div className="mt-4 font-size-h3">{value}</div>
            </div>
          ))}
          <div className="col-lg-6 col-xl-3 p-4 border-bottom overflow-hidden" style={{ position: 'relative' }}>
            <div>状态</div>
            <div className="mt-4 font-size-h3">{stats && stats.status ? '运行中' : '未启动'}</div>
            <i
              className={`si ${stats && stats.status ? 'si-check text-success' : 'si-close text-danger'}`}
              style={{ position: 'absolute', fontSize: 100, right: -20, bottom: -20 }}
            />
          </div>
        </div>
      </Block>

      <Block title="当前作业详情">
        <DataTable
          dataSource={workload}
          pagination={false}
          columns={[
            { title: '队列名称', dataIndex: 'name', key: 'name', render: (v) => queueNames[v] || v },
            { title: '作业量', dataIndex: 'processes', key: 'processes' },
            { title: '任务量', dataIndex: 'length', key: 'length' },
            { title: '占用时间', dataIndex: 'wait', key: 'wait', align: 'right', render: (v) => `${v}s` }
          ]}
        />
      </Block>
    </Spin>
  )
}
