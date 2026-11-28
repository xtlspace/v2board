import React from 'react'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { ConfigProvider } from 'antd'
import zhCN from 'antd/lib/locale/zh_CN'
import dayjs from 'dayjs'
import 'dayjs/locale/zh-cn'

import { getToken, adminBase } from './settings'
import AdminLayout from './layouts/AdminLayout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import ConfigSystem from './pages/ConfigSystem'
import ConfigPayment from './pages/ConfigPayment'
import ConfigTheme from './pages/ConfigTheme'
import ServerManage from './pages/ServerManage'
import ServerGroup from './pages/ServerGroup'
import ServerRoute from './pages/ServerRoute'
import Plan from './pages/Plan'
import Order from './pages/Order'
import Coupon from './pages/Coupon'
import Giftcard from './pages/Giftcard'
import User from './pages/User'
import Notice from './pages/Notice'
import Ticket from './pages/Ticket'
import TicketDetail from './pages/TicketDetail'
import Knowledge from './pages/Knowledge'
import Queue from './pages/Queue'

dayjs.locale('zh-cn')

function RequireAuth({ children }) {
  const location = useLocation()
  if (!getToken()) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }
  return children
}

export default function App() {
  return (
    <ConfigProvider locale={zhCN}>
      <BrowserRouter basename={adminBase || '/'}>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route
            element={
              <RequireAuth>
                <AdminLayout />
              </RequireAuth>
            }
          >
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="/dashboard" element={<Dashboard />} />
            <Route path="/config/system" element={<ConfigSystem />} />
            <Route path="/config/payment" element={<ConfigPayment />} />
            <Route path="/config/theme" element={<ConfigTheme />} />
            <Route path="/server/manage" element={<ServerManage />} />
            <Route path="/server/group" element={<ServerGroup />} />
            <Route path="/server/route" element={<ServerRoute />} />
            <Route path="/plan" element={<Plan />} />
            <Route path="/order" element={<Order />} />
            <Route path="/coupon" element={<Coupon />} />
            <Route path="/giftcard" element={<Giftcard />} />
            <Route path="/user" element={<User />} />
            <Route path="/notice" element={<Notice />} />
            <Route path="/ticket" element={<Ticket />} />
            <Route path="/ticket/:id" element={<TicketDetail />} />
            <Route path="/knowledge" element={<Knowledge />} />
            <Route path="/queue" element={<Queue />} />
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </ConfigProvider>
  )
}
