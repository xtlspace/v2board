<!DOCTYPE html>
<html lang="{{ $theme_config['language'] ?? 'zh-CN' }}" data-color="{{ $theme_config['theme_color'] ?? 'default' }}" data-sidebar="{{ $theme_config['theme_sidebar'] ?? 'light' }}" data-header="{{ $theme_config['theme_header'] ?? 'dark' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no">
    @php
        $colors = [
            'darkblue' => '#3b5998',
            'black' => '#343a40',
            'default' => '#0665d0',
            'green' => '#319795'
        ];
        $language = $theme_config['language'] ?? 'zh-CN';
        if (!in_array($language, ['zh-CN', 'en-US'])) {
            $language = 'zh-CN';
        }
    @endphp
    <meta name="theme-color" content="{{ $colors[$theme_config['theme_color']] ?? '#0665d0' }}">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="/theme/{{ $theme }}/assets/app.css?v={{ $version }}">
    @if (file_exists(public_path("/theme/{$theme}/assets/custom.css")))
        <link rel="stylesheet" href="/theme/{{ $theme }}/assets/custom.css?v={{ $version }}">
    @endif
    <script>
        window.settings = {
            title: {!! json_encode($title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            site_name: {!! json_encode(config('v2board.app_name', 'V2Board'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            assets_path: '/theme/{{ $theme }}/assets',
            theme: {
                sidebar: {!! json_encode($theme_config['theme_sidebar'] ?? 'light', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                header: {!! json_encode($theme_config['theme_header'] ?? 'dark', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                color: {!! json_encode($theme_config['theme_color'] ?? 'default', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            },
            version: {!! json_encode($version, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            background_url: {!! json_encode($theme_config['background_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            description: {!! json_encode($description ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            language: {!! json_encode($language, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            i18n: {},
            logo: {!! json_encode($logo ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
            dialer_proxy_enable: {{ (int) ($dialer_proxy_enable ?? 0) }},
            mock: false
        };
    </script>
    <script src="/theme/{{ $theme }}/assets/i18n/{{ $language }}.js?v={{ $version }}"></script>
</head>

<body>
<div id="root"></div>
{!! $theme_config['custom_html'] ?? '' !!}
<script type="module" src="/theme/{{ $theme }}/assets/app.js?v={{ $version }}"></script>
@if (file_exists(public_path("/theme/{$theme}/assets/custom.js")))
    <script src="/theme/{{ $theme }}/assets/custom.js?v={{ $version }}"></script>
@endif
</body>

</html>
