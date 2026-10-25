<?php

namespace App\Services;

use App\Utils\ConfigCache;
use Illuminate\Support\Facades\File;

class ThemeService
{
    private $path;
    private $theme;

    public function __construct($theme)
    {
        $this->theme = $theme;
        $this->path = $path = public_path('theme/');
    }

    public function init()
    {
        $themeConfigFile = $this->path . "{$this->theme}/config.json";
        if (!File::exists($themeConfigFile)) abort(500, "{$this->theme}主题不存在");
        $themeConfig = json_decode(File::get($themeConfigFile), true);
        if (!isset($themeConfig['configs']) || !is_array($themeConfig)) abort(500, "{$this->theme}主题配置文件有误");
        $configs = $themeConfig['configs'];
        $data = [];
        foreach ($configs as $config) {
            $data[$config['field_name']] = isset($config['default_value']) ? $config['default_value'] : '';
        }

        $exported = var_export($data, 1);
        if (!ConfigCache::write(base_path() . "/config/theme/{$this->theme}.php", "<?php\n return $exported ;")) {
            abort(500, "{$this->theme}初始化失败");
        }

        // 让当前请求立即生效
        config(["theme.{$this->theme}" => $data]);
    }
}
