import React, { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { message, Modal } from 'antd'
import { api, setToken, getToken } from '../api/client'
import { settings } from '../settings'

export default function Login() {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const emailRef = useRef(null)
  const passwordRef = useRef(null)

  useEffect(() => {
    if (getToken()) navigate('/dashboard', { replace: true })
  }, [navigate])

  const login = () => {
    const email = emailRef.current.value.trim()
    const password = passwordRef.current.value
    if (!email || !password) {
      message.warning('请输入邮箱和密码')
      return
    }
    setLoading(true)
    api.login({ email, password }).then((res) => {
      setLoading(false)
      if (res.code !== 200) return
      const data = res.data || {}
      if (!data.is_admin) {
        message.error('该账号不是管理员')
        return
      }
      setToken(data.auth_data)
      navigate('/dashboard', { replace: true })
    })
  }

  const forgot = () => {
    Modal.info({
      title: '忘记密码',
      content: (
        <div>
          <div>在站点目录下执行命令找回密码</div>
          <code>php artisan reset:password 管理员邮箱</code>
        </div>
      ),
      okText: '我知道了'
    })
  }

  return (
    <div id="page-container">
      <main id="main-container">
        <div className="v2board-background" />
        <div className="no-gutters v2board-auth-box">
          <div style={{ maxWidth: 450, width: '100%', margin: 'auto' }}>
            <div className="mx-2 mx-sm-0">
              <div
                className="block block-rounded block-transparent block-fx-pop w-100 mb-0 overflow-hidden bg-image"
                style={{ boxShadow: '0 0.5rem 2rem #0000000d' }}
              >
                <div className="row no-gutters">
                  <div className="col-md-12 order-md-1 bg-white">
                    <div className="block-content block-content-full px-lg-4 py-md-4 py-lg-4">
                      <div className="mb-3 text-center">
                        <a className="font-size-h1" href="javascript:void(0);">
                          {settings.logo ? (
                            <img className="v2board-logo mb-3" src={settings.logo} alt="logo" />
                          ) : (
                            <span className="text-dark">{settings.title || 'V2Board'}</span>
                          )}
                        </a>
                        <p className="font-size-sm text-muted mb-3">登录到管理中心</p>
                      </div>
                      <div className="form-group">
                        <input ref={emailRef} type="text" className="form-control form-control-alt" placeholder="邮箱" autoFocus />
                      </div>
                      <div className="form-group">
                        <input
                          ref={passwordRef}
                          type="password"
                          className="form-control form-control-alt"
                          placeholder="密码"
                          onKeyDown={(e) => e.key === 'Enter' && login()}
                        />
                      </div>
                      <div className="form-group mb-0">
                        <button disabled={loading} type="submit" className="btn btn-block btn-primary font-w400" onClick={login}>
                          {loading ? '登录中...' : (
                            <span>
                              <i className="si si-login mr-1" /> 登入
                            </span>
                          )}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <div className="text-center bg-gray-lighter p-3 px-4">
                  <a onClick={forgot}>忘记密码</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
