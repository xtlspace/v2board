@php
    $manifestPath = public_path('assets/admin-next/.vite/manifest.json');
    $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
    $entry = $manifest['src/main.jsx'] ?? $manifest['index.html'] ?? null;
    $css = $entry && !empty($entry['css']) ? $entry['css'] : [];
@endphp
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no">
    <title>{{ $title }}</title>
    @if ($entry)
        @foreach ($css as $file)
            <link rel="stylesheet" href="/assets/admin-next/{{ $file }}">
        @endforeach
    @endif
    <script>
        window.settings = {
            title: '{{ $title }}',
            theme: {
                sidebar: '{{ $theme_sidebar }}',
                header: '{{ $theme_header }}',
                color: '{{ $theme_color }}',
            },
            version: '{{ $version }}',
            background_url: '{{ $background_url }}',
            logo: '{{ $logo }}',
            secure_path: '{{ $secure_path }}',
            admin_base: '/{{ $secure_path }}'
        }
        window.routerBase = '/{{ $secure_path }}';
    </script>
</head>

<body>
<div id="root"></div>
@if ($entry)
    <script type="module" src="/assets/admin-next/{{ $entry['file'] }}"></script>
@else
    <div style="padding:40px;font-family:sans-serif">
        <h3>新版管理面板尚未构建</h3>
        <p>请在项目根目录执行：<code>cd admin &amp;&amp; npm install &amp;&amp; npm run build</code></p>
    </div>
@endif
</body>

</html>
