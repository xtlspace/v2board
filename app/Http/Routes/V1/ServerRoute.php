<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class ServerRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'server'
        ], function ($router) {
            $router->any('/{class}/{action}', function($class, $action) {
                if (strcasecmp($class, 'uniproxy') !== 0) {
                    abort(404);
                }
                $ctrl = \App::make("\\App\\Http\\Controllers\\V1\\Server\\UniProxyController");
                if (!method_exists($ctrl, $action)) {
                    abort(404);
                }
                return \App::call([$ctrl, $action]);
            });
        });
    }
}
