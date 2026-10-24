<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');

        // Horizon::night();

        // 面板管理员使用自定义 auth_data 鉴权，$request->user() 恒为 null，
        // Horizon 默认的 environment('local') 放行在 APP_ENV=production 下会失效，
        // 这里按 admin 中间件注入的 is_admin 判定放行。
        Horizon::auth(function ($request) {
            if (app()->environment('local')) {
                return true;
            }
            $admin = $request->input('user');
            if (is_array($admin) && !empty($admin['is_admin'])) {
                return true;
            }
            return Gate::check('viewHorizon', [$request->user()]);
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewHorizon', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
}
