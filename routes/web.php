<?php

use App\Services\ThemeService;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function (Request $request) {
    if (config('v2board.app_url') && config('v2board.safe_mode_enable', 0)) {
        if ($request->server('HTTP_HOST') !== parse_url(config('v2board.app_url'))['host']) {
            abort(403);
        }
    }
    $renderParams = [
        'title' => config('v2board.app_name', 'V2Board'),
        'theme' => config('v2board.frontend_theme', 'default'),
        'version' => config('app.version'),
        'description' => config('v2board.app_description', 'V2Board is best'),
        'logo' => config('v2board.logo'),
        'dialer_proxy_enable' => (int) config('v2board.dialer_proxy_enable', 0)
    ];

    if (!config("theme.{$renderParams['theme']}")) {
        $themeService = new ThemeService($renderParams['theme']);
        $themeService->init();
    }

    $renderParams['theme_config'] = config('theme.' . config('v2board.frontend_theme', 'default'));
    return view('theme::' . config('v2board.frontend_theme', 'default') . '.dashboard', $renderParams);
});

//TODO:: 兼容
Route::get('/' . config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))), function () {
    return view('admin_next', [
        'title' => config('v2board.app_name', 'V2Board'),
        'theme_sidebar' => config('v2board.frontend_theme_sidebar', 'light'),
        'theme_header' => config('v2board.frontend_theme_header', 'dark'),
        'theme_color' => config('v2board.frontend_theme_color', 'default'),
        'background_url' => config('v2board.frontend_background_url'),
        'version' => config('app.version'),
        'logo' => config('v2board.logo'),
        'secure_path' => config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
});

if (!empty(config('v2board.subscribe_path'))) {
    Route::get(config('v2board.subscribe_path'), 'V1\\Client\\ClientController@subscribe')->middleware('client');
}

Route::get('/' . config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))) . '/user_portrait', function () {
    return view('admin.user_portrait', [
        'title' => config('v2board.app_name', 'V2Board'),
        'version' => config('app.version'),
        'secure_path' => config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))),
        'logo' => config('v2board.logo'),
        'ip_lookup_url' => config('v2board.ip_lookup_url', 'https://iplark.com/')
    ]);
});

// 新版管理面板（源码位于 admin/，构建产物输出到 public/assets/admin-next）
// 默认后台入口与 SPA 路由都由该视图承载，旧面板资源仍保留在 public/assets/admin/ 便于回退。
Route::get('/' . config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))) . '/{any?}', function () {
    return view('admin_next', [
        'title' => config('v2board.app_name', 'V2Board'),
        'theme_sidebar' => config('v2board.frontend_theme_sidebar', 'light'),
        'theme_header' => config('v2board.frontend_theme_header', 'dark'),
        'theme_color' => config('v2board.frontend_theme_color', 'default'),
        'background_url' => config('v2board.frontend_background_url'),
        'version' => config('app.version'),
        'logo' => config('v2board.logo'),
        'secure_path' => config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
})->where('any', '.*');