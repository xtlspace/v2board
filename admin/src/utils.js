import dayjs from 'dayjs'
import { message } from 'antd'

export function bytes(value) {
  const size = Number(value || 0)
  if (size <= 0) return '0 B'
  if (size >= 1073741824) return `${(size / 1073741824).toFixed(2)} GB`
  if (size >= 1048576) return `${(size / 1048576).toFixed(2)} MB`
  if (size >= 1024) return `${(size / 1024).toFixed(2)} KB`
  return `${size.toFixed(2)} B`
}

export function gbToBytes(value) {
  return Math.round(Number(value || 0) * 1073741824)
}

export function money(value) {
  return `¥${(Number(value || 0) / 100).toFixed(2)}`
}

export function time(value, format = 'YYYY-MM-DD HH:mm:ss') {
  if (!value) return '-'
  return dayjs(Number(value) * 1000).format(format)
}

export function dateText(value) {
  if (!value) return '长期有效'
  return dayjs(Number(value) * 1000).format('YYYY-MM-DD HH:mm:ss')
}

export function copyText(text) {
  const value = String(text === null || text === undefined ? '' : text)
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(value).then(() => message.success('复制成功'))
    return
  }
  const input = document.createElement('textarea')
  input.value = value
  input.style.position = 'fixed'
  input.style.opacity = '0'
  document.body.appendChild(input)
  input.select()
  try {
    document.execCommand('copy')
    message.success('复制成功')
  } finally {
    document.body.removeChild(input)
  }
}

export function toUnix(value) {
  if (!value) return null
  if (typeof value === 'number') return value
  return dayjs(value).unix()
}

export function download(filename, text) {
  const blob = new Blob(['\ufeff' + text], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

export function isJsonString(value) {
  if (typeof value !== 'string') return false
  try {
    JSON.parse(value)
    return true
  } catch (e) {
    return false
  }
}

export const periodLabels = {
  month_price: '月付',
  quarter_price: '季付',
  half_year_price: '半年付',
  year_price: '年付',
  two_year_price: '两年付',
  three_year_price: '三年付',
  onetime_price: '一次性',
  reset_price: '流量重置包'
}

export const orderStatus = {
  0: { text: '待支付', color: 'orange' },
  1: { text: '开通中', color: 'green' },
  2: { text: '已取消', color: 'default' },
  3: { text: '已完成', color: 'blue' },
  4: { text: '已折抵', color: 'purple' }
}

export const commissionStatus = {
  0: { text: '待确认', color: 'orange' },
  1: { text: '发放中', color: 'blue' },
  2: { text: '已发放', color: 'green' },
  3: { text: '已驳回', color: 'red' }
}

export const ticketLevelText = ['低', '中', '高']

export const ticketStatusText = {
  0: '开启',
  1: '待回复',
  2: '待答复',
  3: '关闭'
}

export const routeActionText = {
  block: '禁止访问(域名目标)',
  block_ip: '禁止访问(IP目标)',
  block_port: '禁止访问(端口目标)',
  protocol: '禁止访问(协议)',
  dns: '指定DNS服务器进行解析',
  route: '指定出站服务器(域名目标)',
  route_ip: '指定出站服务器(IP目标)',
  default_out: '自定义默认出站'
}

export const i18nText = {
  'zh-CN': '简体中文',
  'zh-TW': '繁體中文',
  'en-US': 'English',
  'ja-JP': '日本語',
  'vi-VN': 'Tiếng Việt',
  'ko-KR': '한국어'
}

export const resetTrafficMethod = {
  0: '跟随系统设置',
  1: '每月1号',
  2: '按月重置',
  3: '不重置',
  4: '每年1月1日'
}
