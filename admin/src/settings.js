const raw = (typeof window !== 'undefined' && window.settings) || {}

const securePath = String(raw.secure_path || 'admin').replace(/^\/+|\/+$/g, '')

export const settings = {
  title: raw.title || 'V2Board',
  securePath,
  version: raw.version || '',
  logo: raw.logo || '',
  theme: Object.assign({ sidebar: 'dark', header: 'dark', color: 'default' }, raw.theme || {})
}

// 面板部署基路径：预览时为 /{secure_path}/next，正式切换后为 /{secure_path}
export const adminBase = String(raw.admin_base || (securePath ? `/${securePath}/next` : '')).replace(/\/+$/, '')

export const apiBase = `${window.location.origin}/api/v1`
export const securePrefix = `/${securePath}`
export const assetBase = import.meta.env.BASE_URL || '/'

export const portraitUrl = `${securePrefix}/user_portrait`

export const TOKEN_KEY = 'admin_authorization'

export function getToken() {
  return window.localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  window.localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  window.localStorage.removeItem(TOKEN_KEY)
  window.localStorage.removeItem('authorization')
}
