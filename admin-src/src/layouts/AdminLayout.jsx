import React, { useEffect, useState } from 'react'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Dropdown, Menu, Spin, message } from 'antd'
import { menu, titleFor } from '../nav'
import { settings } from '../settings'
import { api, clearToken } from '../api/client'
import { assetBase } from '../settings'

const darkKey = 'admin_dark_mode'

export default function AdminLayout() {
  const location = useLocation()
  const navigate = useNavigate()
  const [user, setUser] = useState({})
  const [loadingUser, setLoadingUser] = useState(true)
  const [showNav, setShowNav] = useState(false)
  const [dark, setDark] = useState(() => window.localStorage.getItem(darkKey) === '1')

  const theme = settings.theme
  const title = titleFor(location.pathname)

  useEffect(() => {
    api.userInfo().then((res) => {
      if (res.code === 200) setUser(res.data || {})
      setLoadingUser(false)
    })
  }, [])

  useEffect(() => {
    const id = 'v2board-admin-theme'
    let link = document.getElementById(id)
    if (!link) {
      link = document.createElement('link')
      link.id = id
      link.rel = 'stylesheet'
      document.head.appendChild(link)
    }
    link.href = `${assetBase}theme/${theme.color || 'default'}.css`
  }, [theme.color])

  useEffect(() => {
    document.title = `${title} - ${settings.title}`
  }, [title])

  useEffect(() => {
    document.body.classList.toggle('v2board-dark', dark)
    window.localStorage.setItem(darkKey, dark ? '1' : '0')
  }, [dark])

  const logout = () => {
    clearToken()
    navigate('/login', { replace: true })
  }

  const go = (path) => {
    navigate(path)
    setShowNav(false)
  }

  // 以当前管理员身份进入用户面板。先同步打开新标签页，避免异步请求后被浏览器拦截。
  const openUserPanel = () => {
    const win = window.open('', '_blank')
    api.post('/user/loginAsSelf').then((res) => {
      if (res.code === 200 && res.data && res.data.auth_data) {
        window.localStorage.setItem('authorization', res.data.auth_data)
        if (win) {
          win.location.href = '/'
        } else {
          window.location.href = '/'
        }
        return
      }
      if (win) win.close()
      if (res.code !== 403) message.warning(res.message || '无法进入用户面板')
    })
  }

  return (
    <div
      id="page-container"
      className={[
        'sidebar-o side-scroll page-header-fixed main-content-boxed side-trans-enabled',
        theme.sidebar === 'dark' ? 'sidebar-dark' : '',
        theme.header === 'dark' ? 'page-header-dark' : '',
        showNav ? 'sidebar-o-xs' : ''
      ].join(' ')}
    >
      <div
        className="v2board-nav-mask"
        style={{ display: showNav ? 'block' : 'none' }}
        onClick={() => setShowNav(false)}
      />
      <nav id="sidebar">
        <div className="smini-hidden bg-header-dark">
          <div className="content-header justify-content-lg-center bg-black-10">
            <a
              className="link-fx font-size-lg text-white"
              href="javascript:void(0);"
              title="进入用户面板"
              onClick={openUserPanel}
            >
              <span className="text-white-75">{settings.title || 'V2Board'}</span>
            </a>
            <div className="d-lg-none">
              <a className="text-white ml-2" href="javascript:void(0);" onClick={() => setShowNav(false)}>
                <i className="fa fa-times-circle" />
              </a>
            </div>
          </div>
        </div>
        <div className="content-side content-side-full">
          <ul className="nav-main">
            {menu.map((item) => {
              if (item.type === 'heading') {
                return (
                  <li className="nav-main-heading" key={item.title}>
                    {item.title}
                  </li>
                )
              }
              if (item.type === 'link') {
                return (
                  <li className="nav-main-item" key={item.title}>
                    <a className="nav-main-link" href={item.href} target="_blank" rel="noreferrer">
                      <i className={`nav-main-link-icon si ${item.icon}`} />
                      <span className="nav-main-link-name">{item.title}</span>
                    </a>
                  </li>
                )
              }
              const active = location.pathname === item.path || (item.path === '/ticket' && location.pathname.startsWith('/ticket/'))
              return (
                <li className="nav-main-item" key={item.path}>
                  <a className={`nav-main-link ${active ? 'active' : ''}`} onClick={() => go(item.path)}>
                    <i className={`nav-main-link-icon si ${item.icon}`} />
                    <span className="nav-main-link-name">{item.title}</span>
                  </a>
                </li>
              )
            })}
          </ul>
        </div>
        <div className="v2board-copyright">
          {settings.title || 'V2Board'} {settings.version ? `v${settings.version}` : ''}
        </div>
      </nav>

      <header id="page-header">
        <div className="content-header" style={{ maxWidth: 'unset' }}>
          <div className="sidebar-toggle">
            <button
              type="button"
              className={theme.header === 'dark' ? 'btn btn-primary mr-1 d-lg-none' : 'btn mr-1 d-lg-none'}
              onClick={() => setShowNav(!showNav)}
            >
              <i className="fa fa-fw fa-bars" />
            </button>
          </div>
          <div className={theme.header === 'dark' ? 'v2board-container-title text-white' : 'v2board-container-title text-black'}>
            {title}
          </div>
          <div>
            <div className="dropdown d-inline-block">
              <button
                type="button"
                className={theme.header === 'dark' ? 'btn btn-primary mr-1' : 'btn mr-1'}
                onClick={() => setDark(!dark)}
                title="暗色模式"
              >
                <i className={dark ? 'far fa fa-moon' : 'far fa fa-sun'} />
              </button>
            </div>
            {loadingUser ? (
              <div className="spinner-grow text-primary" />
            ) : (
              <Dropdown
                placement="bottomRight"
                overlay={
                  <Menu
                    items={[
                      {
                        key: 'logout',
                        label: (
                          <span>
                            登出 <i className="fa fa-fw fa-sign-out-alt text-danger ml-1" />
                          </span>
                        ),
                        onClick: logout
                      }
                    ]}
                  />
                }
              >
                <button type="button" className={theme.header === 'dark' ? 'btn btn-primary' : 'btn'}>
                  <i className="far fa fa-user-circle" />
                  <span className="d-none d-lg-inline ml-1">{user.email}</span>
                  <i className="fa fa-fw fa-angle-down ml-1" />
                </button>
              </Dropdown>
            )}
          </div>
        </div>
      </header>

      <main id="main-container">
        <div className="p-0 p-lg-4">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
