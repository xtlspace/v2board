<?php

namespace App\Http\Controllers\V1\Admin\Server;

use App\Http\Controllers\Controller;
use App\Models\ServerV2node;
use App\Services\ServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageController extends Controller
{
    public function getNodes(Request $request)
    {
        $serverService = new ServerService();
        return response([
            'data' => $serverService->getAllServers()
        ]);
    }

    public function sort(Request $request)
    {
        ini_set('post_max_size', '5m');
        $params = $request->input('v2node');
        if ($params === null) {
            $params = $_POST['v2node'] ?? [];
        }
        if (!is_array($params)) {
            abort(500, '参数错误');
        }
        DB::beginTransaction();
        foreach ($params as $id => $sort) {
            $server = ServerV2node::find($id);
            if (!$server || !$server->update(['sort' => $sort])) {
                DB::rollBack();
                abort(500, '保存失败');
            }
        }
        DB::commit();
        return response([
            'data' => true
        ]);
    }
}
