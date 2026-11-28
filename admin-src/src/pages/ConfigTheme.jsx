import React, { useEffect, useState } from 'react'
import { Input, Modal, Select, message } from 'antd'
import { api } from '../api/client'

const { TextArea } = Input

const THEME_BANNER = 'https://images.unsplash.com/photo-1567095761054-7a02e69e5c43?ixlib=rb-1.2.1&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1374&q=80'

function encodeConfig(value) {
  return window.btoa(unescape(encodeURIComponent(JSON.stringify(value))))
}

export default function ConfigTheme() {
  const [themes, setThemes] = useState({})
  const [active, setActive] = useState('')
  const [loading, setLoading] = useState(true)
  const [settingsOpen, setSettingsOpen] = useState(false)
  const [settingTheme, setSettingTheme] = useState('')
  const [config, setConfig] = useState({})
  const [saving, setSaving] = useState(false)
  const [activating, setActivating] = useState('')

  const load = () => {
    setLoading(true)
    api.get('/theme/getThemes').then((res) => {
      setLoading(false)
      if (res.code === 200) {
        const data = res.data || {}
        setThemes(data.themes || {})
        setActive(data.active || '')
      }
    })
  }

  useEffect(load, [])

  const openSettings = (name) => {
    setSettingTheme(name)
    setConfig({})
    setSettingsOpen(true)
    api.post('/theme/getThemeConfig', { name }).then((res) => {
      if (res.code === 200) setConfig(res.data || {})
    })
  }

  const closeSettings = () => {
    setSettingsOpen(false)
    setSettingTheme('')
    setConfig({})
  }

  const activate = (name) => {
    setActivating(name)
    api.post('/config/save', { frontend_theme: name }).then((res) => {
      setActivating('')
      if (res.code === 200) {
        message.success('主题已激活')
        setActive(name)
        load()
      }
    })
  }

  const save = () => {
    if (!settingTheme) return
    setSaving(true)
    api
      .post('/theme/saveThemeConfig', { name: settingTheme, config: encodeConfig(config) })
      .then((res) => {
        setSaving(false)
        if (res.code === 200) {
          message.success('保存成功')
          closeSettings()
        }
      })
  }

  const fields = (themes[settingTheme] && themes[settingTheme].configs) || []

  return (
    <div>
      <div className="row">
        <div className="col-lg-12">
          <div className="alert alert-warning mb-0 mb-md-4" role="alert">
            <p className="mb-0">
              如果你采用前后分离的方式部署 V2board，那么主题配置将不会生效。了解
              <b>
                <a
                  href="https://docs.v2board.com/use/advanced.html#%E5%89%8D%E7%AB%AF%E5%88%86%E7%A6%BB"
                  target="_blank"
                  rel="noreferrer"
                >
                  前后分离
                </a>
              </b>
            </p>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="block block-transparent mb-3">
          <div className="block-content block-content-full text-center text-muted py-5">加载中...</div>
        </div>
      ) : (
        Object.keys(themes).map((name) => {
          const theme = themes[name] || {}
          const isActive = name === active
          return (
            <div
              className="block block-transparent bg-image mb-0 mb-md-3 bg-primary"
              key={name}
              style={{ backgroundImage: `url("${THEME_BANNER}")` }}
            >
              <div className="block-content block-content-full bg-gd-white-op-l">
                <div className="d-md-flex justify-content-md-between align-items-md-center">
                  <div className="p-2 py-4">
                    <h3 className="font-size-h4 font-w400 text-black mb-1">{name}</h3>
                    <p className="text-black-75 mb-0">{theme.description || '暂无描述'}</p>
                  </div>
                  <div className="p-2 py-4">
                    {isActive ? (
                      <button type="button" className="btn btn-sm rounded-pill btn-outline-light px-3 mr-2" disabled>
                        当前主题
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="btn btn-sm rounded-pill btn-outline-light px-3 mr-2"
                        disabled={activating === name}
                        onClick={() => activate(name)}
                      >
                        {activating === name ? '激活中...' : '激活主题'}
                      </button>
                    )}
                    <button type="button" className="btn btn-sm rounded-pill btn-outline-light px-3" onClick={() => openSettings(name)}>
                      主题设置
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )
        })
      )}

      <Modal
        title={settingTheme ? `配置${settingTheme}主题` : '主题设置'}
        open={settingsOpen}
        onCancel={closeSettings}
        onOk={save}
        okText="确定"
        cancelText="取消"
        confirmLoading={saving}
        width={520}
        destroyOnClose
      >
        {fields.length ? (
          fields.map((field) => (
            <div className="form-group" key={field.field_name}>
              <label style={{ fontWeight: 'bold' }}>{field.label || field.field_name}</label>
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
            </div>
          ))
        ) : (
          <div className="text-muted">{settingTheme ? '该主题没有可配置项' : ''}</div>
        )}
      </Modal>
    </div>
  )
}
