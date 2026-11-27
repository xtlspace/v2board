import React, { useEffect, useState } from 'react'
import { Button, Input, Select, message, Space, Tag } from 'antd'
import { api } from '../api/client'
import { Block } from '../components/ui'

const { TextArea } = Input

function encodeConfig(value) {
  return window.btoa(unescape(encodeURIComponent(JSON.stringify(value))))
}

export default function ConfigTheme() {
  const [themes, setThemes] = useState({})
  const [active, setActive] = useState('')
  const [selected, setSelected] = useState('')
  const [config, setConfig] = useState({})
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)

  const load = () => {
    setLoading(true)
    api.get('/theme/getThemes').then((res) => {
      setLoading(false)
      if (res.code === 200) {
        const data = res.data || {}
        setThemes(data.themes || {})
        setActive(data.active || '')
        setSelected((prev) => prev || data.active || Object.keys(data.themes || {})[0] || '')
      }
    })
  }

  useEffect(load, [])

  useEffect(() => {
    if (!selected) return
    api.post('/theme/getThemeConfig', { name: selected }).then((res) => {
      if (res.code === 200) setConfig(res.data || {})
    })
  }, [selected])

  const fields = (themes[selected] && themes[selected].configs) || []

  const activate = () => {
    api.post('/config/save', { frontend_theme: selected }).then((res) => {
      if (res.code === 200) {
        message.success('主题已激活')
        setActive(selected)
      }
    })
  }

  const save = () => {
    setSaving(true)
    api.post('/theme/saveThemeConfig', { name: selected, config: encodeConfig(config) }).then((res) => {
      setSaving(false)
      if (res.code === 200) {
        message.success('保存成功')
        setConfig(res.data || config)
      }
    })
  }

  return (
    <div>
      <Block title="主题配置">
        <Space wrap>
          <span className="text-muted">当前主题：</span>
          <Tag color="blue">{active || '-'}</Tag>
          <Select
            style={{ width: 240 }}
            value={selected || undefined}
            placeholder="请选择主题"
            onChange={setSelected}
            options={Object.keys(themes).map((name) => ({
              value: name,
              label: `${themes[name].name || name}${themes[name].version ? ` v${themes[name].version}` : ''}`
            }))}
          />
          <Button type="primary" onClick={activate} disabled={!selected || selected === active}>
            激活主题
          </Button>
          <Button onClick={save} loading={saving} disabled={!selected}>
            保存主题设置
          </Button>
        </Space>
        <div className="text-muted font-size-sm mt-3">如果你采用前后分离的方式部署 V2Board，那么主题配置将不会生效。</div>
      </Block>

      <Block title={`主题设置 · ${selected || ''}`}>
        {fields.length ? (
          <div>
            {fields.map((field) => (
              <div className="form-group" key={field.field_name}>
                <label>{field.label || field.field_name}</label>
                {field.field_type === 'select' ? (
                  <Select
                    style={{ width: '100%' }}
                    value={config[field.field_name] !== undefined ? config[field.field_name] : field.default_value}
                    placeholder={field.placeholder}
                    onChange={(value) => setConfig({ ...config, [field.field_name]: value })}
                    options={Object.entries(field.select_options || {}).map(([value, label]) => ({ value, label }))}
                  />
                ) : field.field_type === 'textarea' ? (
                  <TextArea
                    rows={5}
                    value={config[field.field_name] || ''}
                    placeholder={field.placeholder}
                    onChange={(e) => setConfig({ ...config, [field.field_name]: e.target.value })}
                  />
                ) : (
                  <Input
                    value={config[field.field_name] || ''}
                    placeholder={field.placeholder}
                    onChange={(e) => setConfig({ ...config, [field.field_name]: e.target.value })}
                  />
                )}
                {field.placeholder ? <div className="text-muted font-size-sm mt-1">{field.placeholder}</div> : null}
              </div>
            ))}
            <Button type="primary" loading={saving} onClick={save}>
              保存
            </Button>
          </div>
        ) : (
          <div className="text-muted">{loading ? '加载中...' : '请选择带配置项的主题'}</div>
        )}
      </Block>
    </div>
  )
}
