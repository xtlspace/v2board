# V2Board Admin（新版管理面板）

这是 V2Board 当前使用的管理面板源码。旧版编译面板和资源已经移除。

- 技术栈：Vite + React 17 + Ant Design 4.24 + React Router 6 + axios + dayjs + ECharts
- 样式：后台样式位于 `public/assets/admin-theme/`，可直接修改、刷新生效
- 接口：直接对接现有后端 `/api/v1/{secure_path}/...`，登录使用 `/api/v1/passport/auth/login`
- 鉴权：token 存于 `localStorage.admin_authorization`，请求头 `authorization`

## 开发

```bash
cd admin-src
npm install
npm run dev
```

开发服务器默认 `http://localhost:5173`。默认 `secure_path=admin`，接口代理地址请按当前后端环境在 `vite.config.js` 中修改。

## 构建

```bash
cd admin-src
npm run build
```

构建产物输出到 `public/assets/admin-next/`。

> 如果生产环境启用了路由缓存，部署后需要执行 `php artisan route:cache`（或清理缓存）使新版后台路由生效。

## 访问

新版面板已作为默认后台，路径保持不变：

```
https://你的站点/{secure_path}
```

例如 `secure_path=admin` 时为 `/admin`。SPA 内部路由（如 `/admin/user`、`/admin/order`）由 `routes/web.php` 末尾的兜底路由承载。

## 回退

旧编译面板已经移除。需要回退时请使用 Git 历史恢复对应版本。

## 目录

```
src/
  api/client.js        请求封装、鉴权、错误处理、查询串序列化
  components/          通用 UI（Block、表格、抽屉等）
  layouts/AdminLayout  侧边栏 + 顶栏 + 内容区
  pages/               管理页面
  styles/              已迁移到 public/assets/admin-theme/
  nav.js               菜单与页面标题
  settings.js          window.settings 与部署路径
```

## 样式文件（免构建修改）

后台样式已从 Vite 构建中移出，直接放在：

```
public/assets/admin-theme/
  components.chunk.css
  umi.css
  override.css
  static/                # 字体图标
```

`resources/views/admin_next.blade.php` 会在 `<head>` 中按顺序加载这三个 CSS。  
修改其中任何文件后，刷新后台页面即可生效，不需要执行 `npm run build`。

注意：

- React/JSX 页面改动仍然需要 `npm run build`
- `antd/dist/antd.css` 仍在构建产物中
- 新增文章时 `package-lock.json` 已包含 Markdown 编辑器依赖
