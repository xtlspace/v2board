import axios from 'axios'
import { message } from 'antd'
import { apiBase, adminBase, securePrefix, getToken, clearToken, setToken } from '../settings'

export function serializeQuery(params, prefix) {
  const parts = []
  const walk = (key, value) => {
    if (value === null || value === undefined) {
      parts.push(`${encodeURIComponent(key)}=`)
      return
    }
    if (Array.isArray(value)) {
      value.forEach((item, index) => walk(`${key}[${index}]`, item))
      return
    }
    if (typeof value === 'object') {
      Object.keys(value).forEach((k) => walk(`${key}[${k}]`, value[k]))
      return
    }
    parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
  }
  Object.keys(params || {}).forEach((key) => {
    if (params[key] !== undefined) walk(key, params[key])
  })
  return parts.join('&')
}

const http = axios.create({
  baseURL: apiBase,
  timeout: 60000,
  paramsSerializer: { serialize: (params) => serializeQuery(params) }
})

http.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.authorization = token
  return config
})

http.interceptors.response.use(
  (response) => {
    if (typeof response.data === 'string') return { code: response.status, text: response.data }
    return Object.assign({}, response.data, { code: response.status })
  },
  (error) => {
    const response = error.response
    const status = response ? response.status : 0
    const data = (response && response.data) || {}
    if (status === 403) {
      clearToken()
      if (!window.location.pathname.replace(/\/+$/, '').endsWith('/login')) {
        window.location.href = `${adminBase}/login`
      }
      return Promise.resolve({ code: 403, message: '登录已失效' })
    }
    let msg = data.message
    if (!msg && data.errors) {
      const first = Object.values(data.errors)[0]
      msg = Array.isArray(first) ? first[0] : first
    }
    if (!msg) msg = error.message || '网络错误'
    if (status !== 403 && !(error.config && error.config.silent)) message.error(msg)
    return Promise.resolve({ code: status, message: msg })
  }
)

export const api = {
  get: (path, params, config) => http.get(securePrefix + path, { params, ...(config || {}) }),
  post: (path, data, config) => http.post(securePrefix + path, data, config || {}),
  // 站点级接口：登录 / 当前用户
  login: (data) => http.post('/passport/auth/login', data),
  userInfo: () => http.get('/user/info')
}

export { setToken, getToken, clearToken }

export function pickData(res, ok) {
  if (res && res.code === 200) {
    if (typeof ok === 'function') ok(res)
    return res
  }
  return res || { code: 0 }
}

export function errorText(res) {
  return (res && res.message) || '请求失败'
}
