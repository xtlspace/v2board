import React from 'react'
import { settings, portraitUrl } from './settings'

export const menu = [
  { title: '仪表盘', type: 'item', path: '/dashboard', icon: 'si-speedometer' },
  { title: '设置', type: 'heading' },
  { title: '系统配置', type: 'item', path: '/config/system', icon: 'si-equalizer' },
  { title: '支付配置', type: 'item', path: '/config/payment', icon: 'si-credit-card' },
  { title: '主题配置', type: 'item', path: '/config/theme', icon: 'si-magic-wand' },
  { title: '服务器', type: 'heading' },
  { title: '节点管理', type: 'item', path: '/server/manage', icon: 'si-layers' },
  { title: '权限组管理', type: 'item', path: '/server/group', icon: 'si-wrench' },
  { title: '路由管理', type: 'item', path: '/server/route', icon: 'si-shuffle' },
  { title: '财务', type: 'heading' },
  { title: '订阅管理', type: 'item', path: '/plan', icon: 'si-bag' },
  { title: '订单管理', type: 'item', path: '/order', icon: 'si-list' },
  { title: '优惠券管理', type: 'item', path: '/coupon', icon: 'si-present' },
  { title: '礼品卡管理', type: 'item', path: '/giftcard', icon: 'si-star' },
  { title: '用户', type: 'heading' },
  { title: '用户管理', type: 'item', path: '/user', icon: 'si-users' },
  { title: '用户画像', type: 'link', href: portraitUrl, icon: 'si-notebook' },
  { title: '公告管理', type: 'item', path: '/notice', icon: 'si-speech' },
  { title: '工单管理', type: 'item', path: '/ticket', icon: 'si-support' },
  { title: '知识库管理', type: 'item', path: '/knowledge', icon: 'si-bulb' },
  { title: '指标', type: 'heading' },
  { title: '队列监控', type: 'item', path: '/queue', icon: 'si-bar-chart' }
]

export const pageTitles = {
  '/dashboard': '仪表盘',
  '/config/system': '系统配置',
  '/config/payment': '支付配置',
  '/config/theme': '主题配置',
  '/server/manage': '节点管理',
  '/server/group': '权限组管理',
  '/server/route': '路由管理',
  '/plan': '订阅管理',
  '/order': '订单管理',
  '/coupon': '优惠券管理',
  '/giftcard': '礼品卡管理',
  '/user': '用户管理',
  '/notice': '公告管理',
  '/ticket': '工单管理',
  '/knowledge': '知识库管理',
  '/queue': '队列监控'
}

export function titleFor(pathname) {
  if (pathname.startsWith('/ticket/')) return '工单管理'
  return pageTitles[pathname] || settings.title
}
