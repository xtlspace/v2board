## 本分支支持的后端
 - [修改版V2bX](https://github.com/wyx2685/V2bX)
 - [v2node](https://github.com/wyx2685/v2node)

## 原版迁移步骤

按以下步骤进行面板代码文件迁移：

    git remote set-url origin https://github.com/xtlspace/v2board  
    git checkout xtls  
    ./update.sh  


按以下步骤配置缓存驱动为redis，然后刷新设置缓存，重启队列:

    sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=redis/' .env
    php artisan config:clear
    php artisan config:cache
    php artisan horizon:terminate

最后进入后台重新保存主题： 主题配置-选择default主题-主题设置-确定保存

# **V2Board**

- PHP7.3+
- Composer
- MySQL5.5+
- Redis
- Laravel

# 修改点
```
vi app/Http/Controllers/V1/Client/ClientController.php
#将流量和时间改为一条，并放在节点末尾
    private function setSubscribeInfoToServers(&$servers, $user)
    {
        if (!isset($servers[0])) return;
        if (!(int)config('v2board.show_info_to_server_enable', 0)) return;
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : '长期有效';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        array_push($servers, array_merge($servers[0], [
            'name' => "到期时间：{$expiredDate}，剩余流量：{$remainingTraffic}",
        ]));

    }
}

app/Http/Controllers/V1/Guest/PaymentController.php
#关闭充值TG提醒
注释掉这一行
#$telegramService->sendMessageWithAdmin($message);

app/Protocols/ClashVerge.php
增加 mptcp 

$array['mptcp'] = true;

public/theme/default/assets/i18n/zh-CN.js
#修改中文流量达量提示
```
# 增加命令
```
#清理过期订单
php artisan clear:order
```