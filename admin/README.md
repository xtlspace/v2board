# V2Board Admin（新版管理面板）

这是根据仓库中既有的编译产物（`public/assets/admin/umi.js`、`umi.css` 等）重建的可维护前端源码。

- 技术栈：Vite + React 17 + Ant Design 4.24 + React Router 6 + axios + dayjs + ECharts
- 样式：复用原面板的 `components.chunk.css`、`umi.css`、字体图标与四套 theme CSS，保持基本一致的观感
- 接口：直接对接现有后端 `/api/v1/{secure_path}/...`，登录使用 `/api/v1/passport/auth/login`
- 鉴权：token 存于 `localStorage.admin_authorization`，请求头 `authorization`

## 开发

```bash
cd admin
npm install
npm run dev
```

开发服务器默认 `http://localhost:5173`。默认 `secure_path=admin`，接口代理到 `http://127.0.0.1:8080`（可在 `vite.config.js` 修改）。

## 构建

```bash
cd admin
npm run build
```

构建产物输出到 `public/assets/admin-next/`，不会覆盖旧的 `public/assets/admin/`。

> 如果生产环境启用了路由缓存，部署后需要执行 `php artisan route:cache`（或清理缓存）使新版后台路由生效。

## 访问

新版面板已作为默认后台，路径保持不变：

```
https://你的站点/{secure_path}
```

例如 `secure_path=admin` 时为 `/admin`。SPA 内部路由（如 `/admin/user`、`/admin/order`）由 `routes/web.php` 末尾的兜底路由承载。

## 回退

旧面板资源仍保留在 `public/assets/admin/`。需要回退时，把 `routes/web.php` 中默认后台视图改回 `view('admin')`，并移除 `/{secure_path}/{any?}` 兜底路由即可；或直接切回 `xtls` 分支。

## 目录

```
src/
  api/client.js        请求封装、鉴权、错误处理、查询串序列化
  components/          通用 UI（Block、表格、抽屉等）
  layouts/AdminLayout  侧边栏 + 顶栏 + 内容区
  pages/               14 个页面
  styles/              复用的原面板 CSS + override.css
  nav.js               菜单与页面标题
  settings.js          window.settings 与部署路径
```
