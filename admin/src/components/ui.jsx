import React from 'react'
import { Table, Button, Drawer, Space, Tag, Empty } from 'antd'
import { CopyOutlined, ReloadOutlined } from '@ant-design/icons'
import { copyText } from '../utils'

export function Block({ title, options, children, className = '', bodyClassName = 'p-0', header = true }) {
  return (
    <div className={`block block-rounded ${className}`}>
      {header && (title || options) && (
        <div className="block-header">
          <h3 className="block-title">{title}</h3>
          {options ? <div className="block-options">{options}</div> : null}
        </div>
      )}
      <div className={`block-content ${bodyClassName}`}>{children}</div>
    </div>
  )
}

export function Page({ children }) {
  return <div className="v2board-page">{children}</div>
}

export function Field({ label, description, children, className = '' }) {
  return (
    <div className={`form-group ${className}`}>
      {label ? <label>{label}</label> : null}
      {children}
      {description ? <div className="text-muted font-size-sm mt-1">{description}</div> : null}
    </div>
  )
}

export function DataTable({
  columns,
  dataSource,
  loading,
  rowKey = 'id',
  pagination,
  onChange,
  emptyText = '暂无数据',
  scroll = { x: 'max-content' },
  rowClassName
}) {
  return (
    <Table
      rowKey={rowKey}
      size="middle"
      columns={columns}
      dataSource={dataSource || []}
      loading={loading}
      pagination={pagination === false ? false : pagination}
      onChange={onChange}
      scroll={scroll}
      rowClassName={rowClassName}
      locale={{ emptyText: <Empty description={emptyText} /> }}
    />
  )
}

export function Toolbar({ children }) {
  return <Space wrap className="mb-3">{children}</Space>
}

export function CopyButton({ text, children }) {
  return (
    <Button size="small" type="link" icon={<CopyOutlined />} onClick={() => copyText(text)}>
      {children || '复制'}
    </Button>
  )
}

export function RefreshButton({ onClick, loading }) {
  return (
    <Button icon={<ReloadOutlined />} loading={loading} onClick={onClick}>
      刷新
    </Button>
  )
}

export function DrawerForm({ title, open, onClose, onSubmit, submitting, children, width = 560 }) {
  return (
    <Drawer
      title={title}
      open={open}
      onClose={onClose}
      width={width}
      destroyOnClose
      footer={
        <div className="text-right">
          <Space>
            <Button onClick={onClose}>取消</Button>
            <Button type="primary" loading={submitting} onClick={onSubmit}>
              提交
            </Button>
          </Space>
        </div>
      }
    >
      {children}
    </Drawer>
  )
}

export function BoolTag({ value, trueText = '开启', falseText = '关闭' }) {
  return value ? <Tag color="green">{trueText}</Tag> : <Tag>{falseText}</Tag>
}

export function StatusTag({ value, map }) {
  const item = map[value] || { text: value, color: 'default' }
  return <Tag color={item.color}>{item.text}</Tag>
}

export function EmptyBlock({ text = '暂无数据' }) {
  return <Empty description={text} className="py-5" />
}
