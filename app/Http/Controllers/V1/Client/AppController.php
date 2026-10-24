<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Protocols\ClashMeta;
use App\Services\ServerService;
use App\Services\UserService;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function getConfig(Request $request)
    {
        $servers = [];
        $user = $request->user;
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
        }
        $clash = new ClashMeta($user, $servers);
        $yamlContent = $clash->handle(
            base_path('resources/rules/app.clash.yaml'),
            base_path('resources/rules/custom.app.clash.yaml')
        );

        return response($yamlContent, 200)
            ->header('Content-Type', 'text/yaml');
    }

    public function getVersion(Request $request)
    {
        if (strpos((string) $request->header('user-agent'), 'tidalab/4.0.0') !== false
            || strpos((string) $request->header('user-agent'), 'tunnelab/4.0.0') !== false
        ) {
            if (strpos((string) $request->header('user-agent'), 'Win64') !== false) {
                return response([
                    'data' => [
                        'version' => config('v2board.windows_version'),
                        'download_url' => config('v2board.windows_download_url')
                    ]
                ]);
            } else {
                return response([
                    'data' => [
                        'version' => config('v2board.macos_version'),
                        'download_url' => config('v2board.macos_download_url')
                    ]
                ]);
            }
            return;
        }
        return response([
            'data' => [
                'windows_version' => config('v2board.windows_version'),
                'windows_download_url' => config('v2board.windows_download_url'),
                'macos_version' => config('v2board.macos_version'),
                'macos_download_url' => config('v2board.macos_download_url'),
                'android_version' => config('v2board.android_version'),
                'android_download_url' => config('v2board.android_download_url')
            ]
        ]);
    }
}
