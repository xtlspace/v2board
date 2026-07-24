<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class Language
{
    public function handle($request, Closure $next)
    {
        App::setLocale('zh-CN');
        return $next($request);
    }
}
