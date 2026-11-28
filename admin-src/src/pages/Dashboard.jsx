import React, { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Row, Col, Alert, Skeleton } from 'antd'
import { api } from '../api/client'
import EChart from '../components/EChart'

function QuickLink({ icon, title, onClick }) {
  return (
    <a className="block block-bordered block-link-pop text-center mb-0" onClick={onClick}>
      <div className="block-content block-content-full text-center">
        <i className={`fa-2x si ${icon} text-primary d-none d-sm-inline-block mb-3`} />
        <div className="font-w600 text-uppercase">{title}</div>
      </div>
    </a>
  )
}

export default function Dashboard() {
  const navigate = useNavigate()
  const [stat, setStat] = useState({})
  const [config, setConfig] = useState({})
  const [orderChart, setOrderChart] = useState([])
  const [serverToday, setServerToday] = useState([])
  const [serverLast, setServerLast] = useState([])
  const [userToday, setUserToday] = useState([])
  const [userLast, setUserLast] = useState([])
  const [queueStatus, setQueueStatus] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let alive = true
    Promise.all([
      api.get('/stat/getOverride'),
      api.get('/stat/getOrder'),
      api.get('/stat/getServerTodayRank'),
      api.get('/stat/getServerLastRank'),
      api.get('/stat/getUserTodayRank'),
      api.get('/stat/getUserLastRank'),
      api.get('/config/fetch', { key: 'site' }),
      api.get('/system/getQueueStats', null, { silent: true })
    ]).then(([override, order, st, sl, ut, ul, site, queue]) => {
      if (!alive) return
      if (override.code === 200) setStat(override.data || {})
      if (order.code === 200) setOrderChart(order.data || [])
      if (st.code === 200) setServerToday(st.data || [])
      if (sl.code === 200) setServerLast(sl.data || [])
      if (ut.code === 200) setUserToday(ut.data || [])
      if (ul.code === 200) setUserLast(ul.data || [])
      if (site.code === 200) setConfig((site.data && site.data.site) || {})
      if (queue.code === 200) setQueueStatus((queue.data || {}).status)
      setLoading(false)
    })
    return () => {
      alive = false
    }
  }, [])

  const currency = config.currency || 'CNY'

  const lineOption = useMemo(() => {
    const types = []
    const dates = []
    const series = []
    orderChart.forEach((item) => {
      if (!types.includes(item.type)) types.push(item.type)
      if (!dates.includes(item.date)) dates.push(item.date)
      let target = series.find((s) => s.name === item.type)
      if (!target) {
        target = { name: item.type, type: 'line', smooth: true, data: [] }
        series.push(target)
      }
      target.data.push(item.value)
    })
    return {
      tooltip: { trigger: 'axis' },
      legend: { data: types, left: '0', z: 4 },
      grid: { left: '1%', right: '1%', bottom: '3%', containLabel: true },
      xAxis: { type: 'category', boundaryGap: false, data: dates },
      yAxis: { type: 'value' },
      series
    }
  }, [orderChart])

  const barOption = (rows, key) =>
    rows.length
      ? {
          tooltip: { trigger: 'axis', formatter: (params) => `${params[0].value} GB` },
          grid: { top: '1%', left: '1%', right: '1%', bottom: '3%', containLabel: true },
          xAxis: { type: 'value' },
          yAxis: { type: 'category', data: rows.slice().reverse().map((r) => r[key]) },
          series: [{ data: rows.slice().reverse().map((r) => Number(r.total.toFixed(2))), type: 'bar' }]
        }
      : null

  if (loading) {
    return (
      <div className="block block-rounded">
        <div className="block-content">
          <Skeleton active paragraph={{ rows: 8 }} />
        </div>
      </div>
    )
  }

  return (
    <div>
      {stat.ticket_pending_total ? (
        <div className="alert alert-danger" role="alert">
          <p className="mb-0">
            有 {stat.ticket_pending_total} 条工单等待处理{' '}
            <a className="alert-link" href="javascript:void(0)" onClick={() => navigate('/ticket')}>
              立即处理
            </a>
          </p>
        </div>
      ) : null}
      {stat.commission_pending_total ? (
        <div className="alert alert-danger" role="alert">
          <p className="mb-0">
            有 {stat.commission_pending_total} 笔佣金等待确认{' '}
            <a className="alert-link" href="javascript:void(0)" onClick={() => navigate('/order')}>
              立即处理
            </a>
          </p>
        </div>
      ) : null}
      {queueStatus === false ? (
        <div className="alert alert-danger" role="alert">
          <p className="mb-0">当前队列服务运行异常，可能会导致业务无法使用。</p>
        </div>
      ) : null}

      <div className="mb-0 block border-bottom d-none d-sm-block">
        <div className="block-content block-content-full">
          <div className="row no-gutters border">
            <div className="col-sm-6 col-xl-3">
              <QuickLink icon="si-equalizer" title="系统设置" onClick={() => navigate('/config/system')} />
            </div>
            <div className="col-sm-6 col-xl-3">
              <QuickLink icon="si-list" title="订单管理" onClick={() => navigate('/order')} />
            </div>
            <div className="col-sm-6 col-xl-3">
              <QuickLink icon="si-bag" title="订阅管理" onClick={() => navigate('/plan')} />
            </div>
            <div className="col-sm-6 col-xl-3">
              <QuickLink icon="si-users" title="用户管理" onClick={() => navigate('/user')} />
            </div>
          </div>
        </div>
      </div>

      <div className="row no-gutters">
        <div className="col-lg-12">
          <div className="block border-bottom mb-0 v2board-stats-bar">
            <div className="block-content">
              <div className="d-flex align-items-center">
                <div className="pr-4 pr-sm-5 pl-0 pl-sm-3">
                  <i className="fa fa-users fa-2x text-gray-light float-right" />
                  <div className="text-muted mb-1" style={{ width: 120 }}>
                    在线人数
                  </div>
                  <div className="display-4 text-black font-w300 mb-2">{stat.online_user || 0}</div>
                </div>
                <div className="pr-4 pr-sm-5 pl-0 pl-sm-3">
                  <i className="fa fa-chart-line fa-2x text-gray-light float-right" />
                  <p className="text-muted w-75 mb-1">今日收入</p>
                  <p className="display-4 text-black font-w300 mb-2">
                    {stat.day_income ? (stat.day_income / 100).toFixed(2) : '0.00'}
                    <span className="font-size-h5 font-w600 text-muted"> {currency}</span>
                  </p>
                </div>
                <div className="pr-4 pr-sm-5 pl-0 pl-sm-3">
                  <i className="fa fa-user fa-2x text-gray-light float-right" />
                  <div className="text-muted mb-1" style={{ width: 120 }}>
                    实时注册
                  </div>
                  <div className="display-4 text-black font-w300 mb-2">{stat.day_register_total || 0}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-12">
          <div className="block border-bottom mb-0 v2board-stats-bar">
            <div className="block-content block-content-full">
              <div className="d-flex align-items-center">
                <div className="pr-4 pr-sm-5 pl-0 pl-sm-3">
                  <p className="fs-3 text-dark mb-0">
                    {stat.month_income ? (stat.month_income / 100).toFixed(2) : '0.00'} {currency}
                  </p>
                  <p className="text-muted mb-0">本月收入</p>
                </div>
                <div className="px-4 px-sm-5 border-start">
                  <p className="fs-3 text-dark mb-0">
                    {stat.last_month_income ? (stat.last_month_income / 100).toFixed(2) : '0.00'} {currency}
                  </p>
                  <p className="text-muted mb-0">上月收入</p>
                </div>
                <div className="px-4 px-sm-5 border-start">
                  <p className="fs-3 text-dark mb-0">
                    {stat.commission_last_month_payout ? (stat.commission_last_month_payout / 100).toFixed(2) : '0.00'} {currency}
                  </p>
                  <p className="text-muted mb-0">上月佣金支出</p>
                </div>
                <div className="px-4 px-sm-5 border-start">
                  <p className="fs-3 text-dark mb-0">{stat.month_register_total || '-'}</p>
                  <p className="text-muted mb-0">本月新增用户</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-12">
          <div className="block border-bottom mb-0">
            <div className="px-sm-3 pt-sm-3 py-3 clearfix">
              <EChart option={lineOption} style={{ height: 400 }} />
            </div>
          </div>
        </div>

        <div className="row mt-xl-3 w-100">
          {[
            ['今日节点流量排行', serverToday, 'server_name'],
            ['昨日节点流量排行', serverLast, 'server_name'],
            ['今日用户流量排行', userToday, 'email'],
            ['昨日用户流量排行', userLast, 'email']
          ].map(([title, rows, key]) => {
            const option = barOption(rows, key)
            return (
              <div className="col-lg-6 pr-xl-1" key={title}>
                <div className="block border-bottom">
                  <div className="block-header block-header-default">
                    <h3 className="block-title">{title}</h3>
                  </div>
                  <div className="block-content">
                    <div className="px-sm-3 pt-sm-3 py-3 clearfix">
                      {option ? <EChart option={option} style={{ height: 400 }} /> : <div className="text-muted text-center py-5">暂无数据</div>}
                    </div>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
